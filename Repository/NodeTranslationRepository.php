<?php

namespace Kunstmaan\AdminNodeBundle\Repository;

use Kunstmaan\AdminNodeBundle\Entity\HasNodeInterface;
use Kunstmaan\AdminNodeBundle\Entity\Node;
use Kunstmaan\AdminNodeBundle\Entity\NodeTranslation;
use Kunstmaan\AdminBundle\Entity\AddCommand;
use Kunstmaan\AdminBundle\Entity\User as Baseuser;
use Kunstmaan\AdminBundle\Helper\Slugifier;
use Kunstmaan\AdminBundle\Helper\ClassLookup;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * NodeRepository
 *
 */
class NodeTranslationRepository extends EntityRepository
{
    /**
     * Get all childs of a given node
     * @param Node $node
     *
     * @return array
     */
    public function getChildren(Node $node)
    {
        return $this->findBy(array("parent" => $node->getId()));
    }

    /**
     * This returns the node translations that are visible for guest users
     *
     * @return array
     */
    public function getOnlineNodes()
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b')
            ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
            ->where("n.deleted != 1 AND b.online = 1");

        return $qb;
    }

    /**
     * Get the nodetranslation for a node
     * @param HasNodeInterface $hasNode
     *
     * @return NodeTranslation
     */
    public function getNodeTranslationFor(HasNodeInterface $hasNode)
    {
        $nodeVersion = $this->getEntityManager()->getRepository('KunstmaanAdminNodeBundle:NodeVersion')->getNodeVersionFor($hasNode);
        if (!is_null($nodeVersion)) {
            return $nodeVersion->getNodeTranslation();
        }

        return null;
    }

    /**
     * Get the nodetranslation for a given slug string
     * @param string               $slug       The slug
     * @param NodeTranslation|NULL $parentNode The parentnode
     *
     * @return \Kunstmaan\AdminNodeBundle\Entity\NodeTranslation|NULL|object
     */
    public function getNodeTranslationForSlug($slug, NodeTranslation $parentNode = null)
    {
        if (empty($slug)) {
            return $this->getNodeTranslationForSlugPart(null, $slug);
        }

        $slugparts = explode("/", $slug);
        $result = $parentNode;
        foreach ($slugparts as $slugpart) {
            $result = $this->getNodeTranslationForSlugPart($result, $slugpart);
        }

        return $result;
    }

    /**
     * Get the nodetranslation for a given url
     * @param string $urlSlug The full url
     * @param string $locale  The locale
     *
     * @return NodeTranslation|null|object
     */
    public function getNodeTranslationForUrl($urlSlug, $locale)
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b')
            ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
            ->where("n.deleted != 1 AND b.lang = :lang")
            ->addOrderBy('n.sequenceNumber', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->setParameter('lang', $locale);

        if ($urlSlug === null) {
            $qb->andWhere('b.url IS NULL');
        } else {
            $qb->andWhere('b.url = :url');
            $qb->setParameter('url', $urlSlug);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result;
    }

    /**
     * Get all parent nodes
     *
     * @return array
     */
    public function getParentNodeTranslations()
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b')
            ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
            ->where('n.parent IS NULL')
            ->andWhere('n.deleted != 1');

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns the nodetranslation for a given slug
     * @param NodeTranslation|null $parentNode The parentNode
     * @param string               $slugpart   The slug part
     *
     * @return null|object
     */
    private function getNodeTranslationForSlugPart(NodeTranslation $parentNode = null, $slugpart = "")
    {
        if ($parentNode != null) {
            $qb = $this->createQueryBuilder('b')
                ->select('b')
                ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
                ->where('b.slug = :slug and n.parent = :parent')
                ->andWhere('n.deleted != 1')
                ->addOrderBy('n.sequenceNumber', 'DESC')
                ->setFirstResult(0)
                ->setMaxResults(1)
                ->setParameter('slug', $slugpart)
                ->setParameter('parent', $parentNode->getNode()->getId());
            $result = $qb->getQuery()->getOneOrNullResult();

            return $result;
        } else {
            /* if parent is null we should look for slugs that have no parent */
            $qb = $this->createQueryBuilder('t')
                    ->select('t')
                    ->innerJoin('t.node', 'n', 'WITH', 't.node = n.id')
                    ->where('n.deleted != 1 and n.parent IS NULL')
                    ->addOrderBy('n.sequenceNumber', 'DESC')
                    ->setFirstResult(0)
                    ->setMaxResults(1);
            if (empty($slugpart)) {
                $qb->andWhere('t.slug is NULL');
            } else {
                $qb->andWhere('t.slug = :slug');
                $qb->setParameter('slug', $slugpart);
            }
            $result = $qb->getQuery()->getOneOrNullResult();

            return $result;
        }
    }

    /**
     * Create a nodetranslation for a given node
     * @param HasNodeInterface $hasNode The hasNode
     * @param string           $lang    The locale
     * @param Node             $node    The node
     * @param Baseuser         $owner   The user
     *
     * @return \Kunstmaan\AdminNodeBundle\Entity\NodeTranslation
     * @throws \Exception
     */
    public function createNodeTranslationFor(HasNodeInterface $hasNode, $lang, Node $node, Baseuser $owner)
    {
        $em = $this->getEntityManager();
        $classname = ClassLookup::getClass($hasNode);
        if (!$hasNode->getId() > 0) {
            throw new \Exception("the entity of class " . $classname . " has no id, maybe you forgot to flush first");
        }
        $entityrepo = $em->getRepository($classname);
        $nodeTranslation = new NodeTranslation();
        $nodeTranslation->setNode($node);
        $nodeTranslation->setLang($lang);
        $nodeTranslation->setTitle($hasNode->__toString());
        $nodeTranslation->setSlug(Slugifier::slugify($hasNode->__toString(), ''));
        $nodeTranslation->setOnline($hasNode->isOnline());

        $addcommand = new AddCommand($em, $owner);
        $addcommand->execute("new translation for page \"" . $nodeTranslation->getTitle() . "\" with locale: " . $lang, array('entity' => $nodeTranslation));

        $nodeVersion = $em->getRepository('KunstmaanAdminNodeBundle:NodeVersion')->createNodeVersionFor($hasNode, $nodeTranslation, $owner);
        $nodeTranslation->setPublicNodeVersion($nodeVersion);
        $em->persist($nodeTranslation);
        $em->flush();
        $em->refresh($nodeTranslation);
        $em->refresh($node);

        return $nodeTranslation;
    }

    /**
     * Find best match for given URL and locale
     *
     * @param string $urlSlug The slug
     * @param string $locale  The locale
     *
     * @return NodeTranslation
     */
    public function getBestMatchForUrl($urlSlug, $locale)
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult('Kunstmaan\AdminNodeBundle\Entity\NodeTranslation', 'nt');
        $rsm->addFieldResult('nt', 'id', 'id');
        $rsm->addMetaResult('nt', 'node', 'node');
        $rsm->addFieldResult('nt', 'lang', 'lang');
        $rsm->addFieldResult('nt', 'online', 'online');
        $rsm->addFieldResult('nt', 'title', 'title');
        $rsm->addFieldResult('nt', 'slug', 'slug');
        $rsm->addFieldResult('nt', 'url', 'url');
        $rsm->addMetaResult('nt', 'publicNodeVersion', 'publicNodeVersion');
        $rsm->addMetaResult('nt', 'seo', 'seo');

        $query = $em
            ->createNativeQuery(
                'select nt.id, nt.node, nt.lang, nt.online, nt.title, nt.slug, nt.url, nt.publicNodeVersion, nt.seo from nodetranslation nt
                            join node n on n.id = nt.node
                            where n.deleted = 0 and nt.lang = ? and locate(url, ?) = 1 order by length(url) desc limit 1', $rsm);
        $query->setParameter(1, $locale);
        $query->setParameter(2, $urlSlug);
        $translation = $query->getOneOrNullResult();

        return $translation;
    }
}
