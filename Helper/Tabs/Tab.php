<?php

namespace Kunstmaan\NodeBundle\Helper\Tabs;

use Doctrine\ORM\EntityManager;
use Kunstmaan\AdminBundle\Helper\FormHelper;

use Kunstmaan\AdminBundle\Twig\Extension\FormToolsExtension;

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The default tab implementation
 *
 * @todo maybe another name for this?
 */
class Tab implements TabInterface
{

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var AbstractType[]
     */
    protected $types;

    /**
     * @var array
     */
    protected $data;

    /**
     * @param string $title The title
     * @param array  $types The types
     * @param array  $data  The data attached to the types
     */
    public function __construct($title, array $types = array(), array $data = array())
    {
        $this->title = $title;
        $this->types = $types;
        $this->data = $data;
    }

    /**
     * @param FormBuilderInterface $builder The form builder
     */
    public function buildForm(FormBuilderInterface $builder)
    {
        $data = $builder->getData();

        foreach ($this->types as $name => $type) {
            $builder->add($name, $type);
            $data[$name] = $this->data[$name];
        }

        $builder->setData($data);
    }

    /**
     * @param Request $request
     */
    public function bindRequest(Request $request)
    {

    }

    /**
     * @param EntityManager $em
     */
    public function persist(EntityManager $em)
    {
        foreach ($this->data as $item) {
            $em->persist($item);
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param FormView $formView
     *
     * @return array
     */
    public function getFormErrors(FormView $formView)
    {
        $formViews = array();
        foreach ($this->types as $name => $type) {
            $formViews[] = $formView[$name];
        }

        $formHelper = $this->getFormHelper();
        return $formHelper->getRecursiveErrorMessages($formViews);
    }

    /**
     * @var FormHelper
     */
    private $formHelper;

    /**
     * @return FormHelper
     */
    protected function getFormHelper()
    {
        if (is_null($this->formHelper)) {
            $this->formHelper = new FormHelper();
        }

        return $this->formHelper;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return 'KunstmaanNodeBundle:Tabs:tab.html.twig';
    }

    /**
     * @param string $identifier
     *
     * @return TabInterface
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param string $title
     *
     * @return TabInterface
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param string       $name
     * @param AbstractType $type
     * @param null         $data
     */
    public function addType($name, AbstractType $type, $data = null)
    {
        $this->types[$name] = $type;
        $this->data[$name] = $data;
    }

    /**
     * @return AbstractType[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function getExtraParams(Request $request)
    {
        return array();
    }
}
