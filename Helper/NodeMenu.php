<?php

namespace Kunstmaan\NodeBundle\Helper;

use Kunstmaan\AdminBundle\Helper\Security\Acl\AclHelper;
use Kunstmaan\AdminBundle\Helper\Security\Acl\Permission\PermissionMap;

use Doctrine\ORM\EntityManager;

use Symfony\Component\Security\Core\SecurityContextInterface;

use Kunstmaan\AdminBundle\Entity\User;
use Kunstmaan\NodeBundle\Entity\HasNodeInterface;
use Kunstmaan\NodeBundle\Entity\Node;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;

/**
 * NodeMenu
 */
class NodeMenu
{

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var AclHelper
     */
    private $aclHelper;

    /**
     *
     * @var string
     */
    private $lang;

    /**
     *
     * @var array
     */
    private $topNodeMenuItems = array();

    /**
     * @var NodeMenuItem[]
     */
    private $breadCrumb = array();

    /**
     * @var bool
     */
    private $includeOffline = false;

    /**
     * @var bool
     */
    private $includeHiddenFromNav = false;

    /**
     * @var string
     */
    private $permission = null;

    /**
     * @var mixed
     */
    private $user = null;

    /**
     * @param EntityManager            $em                   The entity manager
     * @param SecurityContextInterface $securityContext      The security context
     * @param AclHelper                $aclHelper            The ACL helper
     * @param string                   $lang                 The language
     * @param Node|null                $currentNode          The node
     * @param string                   $permission           The permission
     * @param bool                     $includeOffline       Include offline pages
     * @param bool                     $includeHiddenFromNav Include hidden pages
     */
    public function __construct(EntityManager $em, SecurityContextInterface $securityContext, AclHelper $aclHelper, $lang, Node $currentNode = null, $permission = PermissionMap::PERMISSION_VIEW, $includeOffline = false, $includeHiddenFromNav = false)
    {
        $this->em = $em;
        $this->securityContext = $securityContext;
        $this->aclHelper = $aclHelper;
        $this->lang = $lang;
        $this->includeOffline = $includeOffline;
        $this->includeHiddenFromNav = $includeHiddenFromNav;
        $this->permission = $permission;
        $tempNode = $currentNode;

        //Breadcrumb
        $nodeBreadCrumb = array();
        while ($tempNode) {
            array_unshift($nodeBreadCrumb, $tempNode);
            $tempNode = $tempNode->getParent();
        }
        /* @var NodeMenuItem $parentNodeMenuItem */
        $parentNodeMenuItem = null;
        /* @var Node $nodeBreadCrumbItem */
        foreach ($nodeBreadCrumb as $nodeBreadCrumbItem) {
            $nodeTranslation = $nodeBreadCrumbItem->getNodeTranslation($this->lang, $this->includeOffline);
            if (!is_null($nodeTranslation)) {
                $nodeMenuItem = new NodeMenuItem($nodeBreadCrumbItem, $nodeTranslation, $parentNodeMenuItem, $this);
                $this->breadCrumb[] = $nodeMenuItem;
                $parentNodeMenuItem = $nodeMenuItem;
            }
        }

        $this->user = $this->securityContext->getToken()->getUser();

        //topNodes
        $topNodes = $this->em->getRepository('KunstmaanNodeBundle:Node')->getTopNodes($this->lang, $permission, $this->aclHelper, $includeHiddenFromNav);
        /* @var Node $topNode */
        foreach ($topNodes as $topNode) {
            $nodeTranslation = $topNode->getNodeTranslation($this->lang, $this->includeOffline);
            if (!is_null($nodeTranslation)) {
                if (sizeof($this->breadCrumb)>0 && $this->breadCrumb[0]->getNode()->getId() == $topNode->getId()) {
                    $this->topNodeMenuItems[] = $this->breadCrumb[0];
                } else {
                    $this->topNodeMenuItems[] = new NodeMenuItem($topNode, $nodeTranslation, null, $this);
                }
            }
        }
    }

    /**
     * @return NodeMenuItem[]
     */
    public function getTopNodes()
    {
        return $this->topNodeMenuItems;
    }

    /**
     * @return NodeMenuItem|null
     */
    public function getCurrent()
    {
        if (sizeof($this->breadCrumb)>0) {
            return $this->breadCrumb[sizeof($this->breadCrumb)-1];
        }

        return null;
    }

    /**
     * @param int $depth
     *
     * @return NodeMenuItem|null
     */
    public function getActiveForDepth($depth)
    {
        if (sizeof($this->breadCrumb)>=$depth) {
            return $this->breadCrumb[$depth-1];
        }

        return null;
    }

    /**
     * @return NodeMenuItem[]
     */
    public function getBreadCrumb()
    {
        return $this->breadCrumb;
    }

    /**
     * @param NodeTranslation $parentNode The parent node
     * @param string          $slug       The slug
     *
     * @return NodeTranslation
     */
    public function getNodeBySlug(NodeTranslation $parentNode, $slug)
    {
        return $this->em->getRepository('KunstmaanNodeBundle:NodeTranslation')->getNodeTranslationForSlug($slug, $parentNode);
    }

    /**
     * @param string                                        $internalName The internal name
     * @param NodeTranslation|NodeMenuItem|HasNodeInterface $parent       The parent
     *
     * @return NodeMenuItem|null
     */
    public function getNodeByInternalName($internalName, $parent = null)
    {
        $node = null;

        if (!is_null($parent)) {
            if ($parent instanceof NodeTranslation) {
                $parent = $parent->getNode();
            } elseif ($parent instanceof NodeMenuItem) {
                $parent = $parent->getNode();
            } elseif ($parent instanceof HasNodeInterface) {
                $parent = $this->em->getRepository('KunstmaanNodeBundle:Node')->getNodeFor($parent);
            }
            $node = $this->em->getRepository('KunstmaanNodeBundle:Node')->findOneBy(array('internalName' => $internalName, 'parent' => $parent->getId()));
            if (is_null($node)) {
                $nodes = $this->em->getRepository('KunstmaanNodeBundle:Node')->findBy(array('internalName' => $internalName));
                /* @var Node $n */
                foreach ($nodes as $n) {
                    $p = $n;
                    while (is_null($node) && !is_null($p->getParent())) {
                        $pParent = $p->getParent();
                        if ($pParent->getId() == $parent->getId()) {
                            $node = $n;
                            break;
                        }
                        $p = $pParent;
                    }
                }
            }
        } else {
            $node = $this->em->getRepository('KunstmaanNodeBundle:Node')->findOneBy(array('internalName' => $internalName));
        }
        if (!is_null($node)) {
            $nodeTranslation = $node->getNodeTranslation($this->lang, $this->includeOffline);
            if (!is_null($nodeTranslation)) {
                return $this->getNodemenuForNodeTranslation($nodeTranslation);
            }
        }

        return null;
    }

    /**
     * @param NodeTranslation $nodeTranslation
     *
     * @return NodeMenuItem|NULL
     */
    private function getNodemenuForNodeTranslation(NodeTranslation $nodeTranslation)
    {
        if (!is_null($nodeTranslation)) {
            $tempNode = $nodeTranslation->getNode();
            //Breadcrumb
            $nodeBreadCrumb = array();
            $parentNodeMenuItem = null;
            while ($tempNode && is_null($parentNodeMenuItem)) {
                array_unshift($nodeBreadCrumb, $tempNode);
                $tempNode = $tempNode->getParent();
                if (!is_null($tempNode)) {
                    $parentNodeMenuItem = $this->getBreadCrumbItemByNode($tempNode);
                }
            }
            $nodeMenuItem = null;
            /* @var Node $nodeBreadCrumbItem */
            foreach ($nodeBreadCrumb as $nodeBreadCrumbItem) {
                $breadCrumbItemFromMain = $this->getBreadCrumbItemByNode($nodeBreadCrumbItem);
                if (!is_null($breadCrumbItemFromMain)) {
                    $parentNodeMenuItem = $breadCrumbItemFromMain;
                }
                $nodeTranslation = $nodeBreadCrumbItem->getNodeTranslation($this->lang, $this->includeOffline);
                if (!is_null($nodeTranslation)) {
                    $nodeMenuItem = new NodeMenuItem($nodeBreadCrumbItem, $nodeTranslation, $parentNodeMenuItem, $this);
                    $parentNodeMenuItem = $nodeMenuItem;
                }
            }

            return $nodeMenuItem;
        }

        return null;
    }

    /**
     * @param Node $node
     *
     * @return NodeMenuItem|NULL
     */
    private function getBreadCrumbItemByNode(Node $node)
    {
        foreach ($this->breadCrumb as $breadCrumbItem) {
            if ($breadCrumbItem->getNode()->getId() == $node->getId()) {
                return $breadCrumbItem;
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function isIncludeOffline()
    {
        return $this->includeOffline;
    }

    /**
     * @return string
     */
    public function getPermission()
    {
        return $this->permission;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return SecurityContextInterface
     */
    public function getSecurityContext()
    {
        return $this->securityContext;
    }

    /**
     * @return AclHelper
     */
    public function getAclHelper()
    {
        return $this->aclHelper;
    }

    /**
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return bool
     */
    public function isIncludeHiddenFromNav()
    {
        return $this->includeHiddenFromNav;
    }

}
