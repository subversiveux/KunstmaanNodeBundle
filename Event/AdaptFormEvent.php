<?php

namespace Kunstmaan\NodeBundle\Event;

use Symfony\Component\EventDispatcher\Event;

use Kunstmaan\NodeBundle\Entity\NodeVersion;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\NodeBundle\Entity\Node;
use Kunstmaan\NodeBundle\Entity\HasNodeInterface;
use Kunstmaan\NodeBundle\Helper\Tabs\TabPane;
use Symfony\Component\HttpFoundation\Request;

/**
 * The event to pass metadata if the adaptForm event is triggered
 */
class AdaptFormEvent extends Event
{

    /**
     * @var TabPane
     */
    private $tabPane;

    /**
     * @var HasNodeInterface
     */
    private $page;

    /**
     * @var Node
     */
    private $node;

    /**
     * @var NodeTranslation
     */
    private $nodeTranslation;

    /**
     * @var NodeVersion
     */
    private $nodeVersion;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Request          $request
     * @param TabPane          $tabPane         The tab pane
     * @param HasNodeInterface $page            The page
     * @param Node             $node            The node
     * @param NodeTranslation  $nodeTranslation The node translation
     * @param NodeVersion      $nodeVersion     The node version
     */
    public function __construct(Request $request, TabPane $tabPane, HasNodeInterface $page, Node $node, NodeTranslation $nodeTranslation, NodeVersion $nodeVersion)
    {
        $this->request = $request;
        $this->tabPane = $tabPane;
        $this->page = $page;
        $this->node = $node;
        $this->nodeTranslation = $nodeTranslation;
        $this->nodeVersion = $nodeVersion;
    }

    /**
     * @return Node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @return NodeTranslation
     */
    public function getNodeTranslation()
    {
        return $this->nodeTranslation;
    }

    /**
     * @return NodeVersion
     */
    public function getNodeVersion()
    {
        return $this->nodeVersion;
    }

    /**
     * @return HasNodeInterface
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return TabPane
     */
    public function getTabPane()
    {
        return $this->tabPane;
    }

    public function getRequest()
    {
        return $this->request;
    }

}
