<?php

namespace DoctrineDbalUtil\UrlMultiTaxonomy\PagerController\Controller;

// use AppBundle\Form\URLForm;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
    // New in version 3.2: The functionality to get the user via the method signature was introduced in Symfony 3.2. You can still retrieve it by calling $this->getUser() if you extend the Controller class.
    // http://symfony.com/doc/current/security.html#retrieving-the-user-object

use Symfony\Component\Templating\EngineInterface;
// TODO: Remove Twig in filename

use Twig_SimpleFunction;
/**
 * Url controller.
 *
 * @Route("url")
 */
class UrlController
{
    /**
     * Lists all uRL entities.
     *
     * @Route("/", name="url_index")
     * @Method("GET")
     */
    public static function indexAction(
        Request $request, // used by pager
        UserInterface $user,
        \RaphiaDBAL $model,
        EngineInterface $templating
    )
    {
        return new Response($templating->render('AppBundle:url:index.html.twig', [
            'uRLs' => $model
                ->getUrlIndexPager('url', 'uuid', 'url_uuid',
                    'owned_url', 'uuid', 'owned_url_uuid',
                    // 'link_owned_url_user', 'user_uuid', 'uuid', $conn->quoteIdentifier('user'),
                    'link_owned_url_user', 'user_uuid', 'uuid', 'http_user',
                    ['uuid' => $user->getId()])
                ->setMaxPerPage(2) // 100
                ->setCurrentPage($request->query->getInt('page', 1))
            ,
        ]));
    }


    /**
     * Creates a new uRL entity.
     *
     * @Route("/new", name="url_new")
     * @Method({"GET", "POST"})
     */
    public static function newAction(
        Request $request, // used by form
        UserInterface $user,
        UrlGeneratorInterface $urlGenerator,
        \RaphiaDBAL $model,
        FormFactoryInterface $formFactory,
        EngineInterface $templating
    )
    {
        $form = $formFactory->create(URLForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO: rewrite with new postgres in Debian 9 using "ON CONFLICT"
            $url_uuid = $model->getByUnique('url', $form->getData())['uuid'];
            if (null == $url_uuid):
                $url_uuid = $model->insert_url_returning_uuid('url', $form->getData())['uuid']; // TODO: verify if already exists
            endif;
            $owned_url_uuid = $model->insert_returning_uuid('owned_url', ['url_uuid' => $url_uuid])['uuid'];
            $model->namespace_insert('link_owned_url_user', [
                'owned_url_uuid' => $owned_url_uuid,
                'user_uuid' => $user->getId(),
            ], $user->getId(), $owned_url_uuid);

            return new RedirectResponse($urlGenerator->generate('url_show', ['uuid' => $owned_url_uuid]));
        }

        return new Response($templating->render('AppBundle:url:new.html.twig', [
            'form' => $form->createView(),
        ]));
    }
    // SELECT CASE EXISTS (SELECT uuid FROM url WHERE url = 'http://php.net/')
    //     WHEN false THEN (INSERT INTO url (uuid, url) VALUES (uuid_generate_v5(uuid_ns_url(), 'http://php.net/'), 'http://php.net/') RETURNING uuid)
    //     WHEN true  THEN (SELECT uuid FROM url WHERE url = 'http://php.net/')
    //     ELSE (INSERT INTO url (uuid, url) VALUES (uuid_generate_v5(uuid_ns_url(), 'http://php.net/'), 'http://php.net/') RETURNING uuid)
    // END;
    
    // INSERT INTO url (uuid, url) VALUES (uuid_generate_v5(uuid_ns_url(), 'http://php.net/'), 'http://php.net/')
    //     ON CONFLICT (uuid) DO NOTHING RETURNING uuid;


    /**
     * Displays a form to edit an existing uRL entity.
     *
     * @Route("/edit/{uuid}", name="url_edit")
     * @Method({"GET", "POST"})
     */
    public static function editAction(
        $uuid,
        Request $request, // used by form
        UrlGeneratorInterface $urlGenerator,
        \RaphiaDBAL $model,
        FormFactoryInterface $formFactory,
        EngineInterface $templating
    )
    {
        $uuida = ['uuid' => $uuid]; // That would be nice if it could be generated by the "router kernel" (?)
        $uRL = $model->getByUnique('owned_url', $uuida);
        // $uRL = ['url' => $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url']];

        $deleteForm = self::createDeleteForm($uRL, $urlGenerator, $formFactory);
        $editForm = $formFactory->create(URLForm::class, ['url' => $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url']]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $form_url = ['url' => $editForm->getData()['url']];
            // TODO: if url has changed -> new uuid ?!
            // url garbage collection
            // TODO: rewrite with new postres in Debian 9 using "ON CONFLICT"
            $url_uuid = $model->getByUnique('url', $form_url)['uuid'];
            if (null == $url_uuid):
                $url_uuid = $model->insert_url_returning_uuid('url', $form_url)['uuid']; // TODO: verify if already exists
            endif;
            $model->updateByUnique('owned_url', $uuida, ['url_uuid' => $url_uuid]);

            return new RedirectResponse($urlGenerator->generate('url_show', $uuida));
            // TODO: be sure to use updated version of UUID?!
        }

        return new Response($templating->render('AppBundle:url:edit.html.twig', array(
            'uRL' => $uRL,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        )));
    }
    // if ($uRL['url_uuid'] <> $url_uuid):
    // SELECT CASE EXISTS (SELECT * FROM owned_url WHERE url_uuid = $uRL['url_uuid'])
    //     WHEN false THEN (DELETE url WHERE uuid = $uRL['url_uuid'])
    // END;
    // To insert just before redirection

    /**
     * Finds and displays a uRL entity.
     *
     * @Route("/{uuid}", name="url_show")
     * @Method("GET")
     */
    public function showAction( // static //////////////////////////////
        $uuid,
        UserInterface $user,
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        \RaphiaDBAL $model,
        FormFactoryInterface $formFactory,
        EngineInterface $templating
    )
    {
        $uRL = $model->getByUnique('owned_url', ['uuid' => $uuid]);
        $uRL['url'] = $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url'];

        //^ TODO: SECURITY AUTHORIZATION

        $deleteForm = self::createDeleteForm($uRL, $urlGenerator, $formFactory);
        $taxonomyForm = self::createAddTaxonomyTermForm($uRL, $urlGenerator, $formFactory);
        // $emptyTaxonomyForm = $this->createAddGivenTaxonomyTermEmptyForm($uRL); // tmp todelete

        return new Response($templating->render('AppBundle:url:show.html.twig', [
            'uRL' => $uRL,
            'delete_form' => $deleteForm->createView(),
            'taxonomy_form' => $taxonomyForm->createView(),
            'terms' => $model
                ->getManyToManyWherePager('taxonomy_tree', 'uuid',
                    'taxonomy_tree_uuid', 'link_taxonomy_tree_user',
                    // 'user_uuid', 'uuid', $conn->quoteIdentifier('user'),
                    'user_uuid', 'uuid', 'http_user',
                    ['uuid' => $user->getId()], 'base.term')
                ->setMaxPerPage(2) // 100
                ->setCurrentPage($request->query->getInt('term_page', 1))
                , // TODO use dependency injection Container to search db only when needed!
            // 'taxonomy_form_generator' => new Twig_SimpleFunction('taxonomy_form_object', function ($uuids) {return $this->createAddGivenTaxonomyTermEmptyForm($uuids)->createView();}),
            'taxonomy_form_object' => new FormGenerator($formFactory->createBuilder(), $urlGenerator),
            'controller' => $this, /////////////////////////////////////!
            'f' => function (array $uuids) {return $this->createAddGivenTaxonomyTermEmptyFormView($uuids);},
            // 'clo' => function () {return function (array $uuids) {return $this->createAddGivenTaxonomyTermEmptyFormView($uuids);};},
            'fb' => $this->createFormBuilder(),
            'ff' => $this->container->get('form.factory'),
            'model' => $model,
        ]));
    }


    /**
     * Deletes a uRL entity.
     *
     * @Route("/{uuid}", name="url_delete")
     * @Method("DELETE")
     */
    public static function deleteAction(
        $uuid,
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        \RaphiaDBAL $model,
        FormFactoryInterface $formFactory
    )
    {
        // TODO: authorization
        
        $uRL = $model->getByUnique('owned_url', ['uuid' => $uuid]);

        $form = self::createDeleteForm($uRL, $urlGenerator, $formFactory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model->deleteByUnique('owned_url', ['uuid' => $uuid]);
        }

        return new RedirectResponse($urlGenerator->generate('url_index'));
    }
    // SELECT CASE EXISTS (SELECT * FROM owned_url WHERE url_uuid = $uRL['url_uuid'])
    //     WHEN false THEN (DELETE url WHERE uuid = $uRL['url_uuid'])
    // END;
    // PREPARE url_garbage_collect(uuid) AS
    //     SELECT CASE EXISTS (SELECT * FROM owned_url WHERE url_uuid = $1)
    //         WHEN false THEN (DELETE FROM url WHERE uuid = $1) -- syntax error near from
    //     END;
    // Anyways one should implement deletion date field before really deleting...


    /**
     * Attach a taxonomy term to a uRL entity (leafs part).
     *
     * @Route("/taxo/{owned_url_uuid}/{taxo_uuid}", name="url_attach_taxonomy_term_leaf")
     * @Method({"POST"})
     */
    public function attachTaxonomyTermLeafAction__unused(Request $request, UserInterface $user, $owned_url_uuid, $taxo_uuid)
    {
        $model = $this->container->get('raphia_model');
        // $uRL = $model->getByUnique('owned_url', ['uuid' => $url_uuid]);
        // $uRL['url'] = $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url'];

        // TODO: authorization
        
        // $taxonomyForm = $this->createAddTaxonomyTermForm($uRL);
        // $taxonomyForm->handleRequest($request);

        // if ($taxonomyForm->isSubmitted() && $taxonomyForm->isValid()) {
            // if ($taxonomyForm->get('attachTerm')->isClicked()):
                $term = $model->getByUnique('taxonomy_tree', ['uuid' => $taxo_uuid]);
                // if (null != $term):
                    $model->insert_uuid4('link_owned_url_taxonomy', ['owned_url_uuid' => $owned_url_uuid, 'taxonomy_uuid' => $term['synonym_uuid']]);
                // endif;
            // elseif ($taxonomyForm->get('redirectToShow')->isClicked()):
                // return $this->redirectToRoute('url_show', ['uuid' => $uuid]);
            // endif;
        // }

        return $this->redirectToRoute('url_show', ['uuid' => $owned_url_uuid]);
        //return $this->render('AppBundle:url:taxonomy.html.twig', [
            //'uRL' => $uRL,
            //// 'delete_form' => $deleteForm->createView(),
            //'taxonomy_form' => $taxonomyForm->createView(),
            //'terms' => $this
                //->container->get('raphia_model')
                //->getManyToManyWhereTraversable('taxonomy_tree', 'uuid', 'taxonomy_tree_uuid', 'link_taxonomy_tree_user', 'user_uuid', 'uuid', 'user', ['uuid' => $user->getId()]),
            //'model' => $model,
        //]);
    }


    /**
     * Detach a taxonomy term to a uRL entity (leafs part).
     *
     * @Route("/taxo/{url_uuid}/{taxo_uuid}", name="url_detach_taxonomy_term")
     * @Method({"DELETE"})
     */
    public function detachTaxonomyTermAction__unused(Request $request, UserInterface $user, $url_uuid, $taxo_uuid)
    {
        $model = $this->container->get('raphia_model');
        // $uRL = $model->getByUnique('owned_url', ['uuid' => $url_uuid]);
        // $uRL['url'] = $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url'];

        // TODO: authorization
        
        // $taxonomyForm = $this->createAddTaxonomyTermForm($uRL);
        // $taxonomyForm->handleRequest($request);

        // if ($taxonomyForm->isSubmitted() && $taxonomyForm->isValid()) {
            // if ($taxonomyForm->get('attachTerm')->isClicked()):
                $term = $model->getByUnique('taxonomy_tree', ['uuid' => $taxo_uuid]);
                // if (null != $term):
                    $model->deleteByUnique('link_owned_url_taxonomy', ['owned_url_uuid' => $url_uuid, 'taxonomy_uuid' => $term['synonym_uuid']]);
                // endif;
            // elseif ($taxonomyForm->get('redirectToShow')->isClicked()):
                // return $this->redirectToRoute('url_show', ['uuid' => $uuid]);
            // endif;
        // }

        return $this->redirectToRoute('url_show', ['uuid' => $url_uuid]);
        //return $this->render('AppBundle:url:taxonomy.html.twig', [
            //'uRL' => $uRL,
            //// 'delete_form' => $deleteForm->createView(),
            //'taxonomy_form' => $taxonomyForm->createView(),
            //'terms' => $this
                //->container->get('raphia_model')
                //->getManyToManyWhereTraversable('taxonomy_tree', 'uuid', 'taxonomy_tree_uuid', 'link_taxonomy_tree_user', 'user_uuid', 'uuid', 'user', ['uuid' => $user->getId()]),
            //'model' => $model,
        //]);
    }


    /**
     * Attach a taxonomy term to a uRL entity (index part).
     *
     * @Route("/taxo/{uuid}", name="url_attach_taxonomy_term_index")
     * @Method({"GET", "POST"})
     */
    public function attachTaxonomyTermIndexAction__unused(Request $request, UserInterface $user, $uuid)
    {
        $model = $this->container->get('raphia_model');
        $uRL = $model->getByUnique('owned_url', ['uuid' => $uuid]);
        $uRL['url'] = $model->getByUnique('url', ['uuid' => $uRL['url_uuid']])['url'];

        // TODO: authorization
        
        $taxonomyForm = $this->createAddTaxonomyTermForm($uRL);
        $taxonomyForm->handleRequest($request);

        if ($taxonomyForm->isSubmitted() && $taxonomyForm->isValid()) {
            // if ($taxonomyForm->get('attachTerm')->isClicked()):
                $term = $model->getByUnique('taxonomy_tree', $taxonomyForm->getData());
                if (null != $term):
                    $model->insert('link_owned_url_taxonomy', ['url_uuid' => $uuid, 'taxonomy_uuid' => $term['synonym_uuid']]);
                endif;
            // elseif ($taxonomyForm->get('redirectToShow')->isClicked()):
                // return $this->redirectToRoute('url_show', ['uuid' => $uuid]);
            // endif;
        }

        return $this->render('AppBundle:url:taxonomy.html.twig', [
            'uRL' => $uRL,
            // 'delete_form' => $deleteForm->createView(),
            'taxonomy_form' => $taxonomyForm->createView(),
            'model' => $model,
        ]);
    }


    /**
     * Creates a form to delete a uRL entity.
     *
     * @param array $uRL The uRL array
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private static function createDeleteForm(
        array $uRL,
        UrlGeneratorInterface $urlGenerator,
        FormFactoryInterface $formFactory
    )
    {
        return $formFactory->createBuilder()
            ->setAction($urlGenerator->generate('url_delete', $uRL))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }


    /**
     * Creates a form to collect and add a taxonomy term to a uRL entity.
     *
     * @param array $uRL The uRL array
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private static function createAddTaxonomyTermForm(
        array $uRL,
        UrlGeneratorInterface $urlGenerator,
        FormFactoryInterface $formFactory
    )
    {
        return $formFactory->createBuilder()
            ->add('term', TextType::class, [
                'attr' => [
                    'autofocus' => true,
                ],
            ])
            // ->add('attachTerm', SubmitType::class, array('label' => 'Attach a taxonomy term'))
            // ->add('redirectToShow', SubmitType::class, array('label' => 'Cancel and return to term page'))
            ->setAction($urlGenerator->generate('url_attach_taxonomy_term_index', $uRL))
            ->getForm()
        ;
    }


    /**
     * Creates a form to add a taxonomy term given in API argument to a uRL entity.
     *
     * @param array $uRL The uRL array
     *
     * @return \Symfony\Component\Form\Form The form
     */
    public static function createAddGivenTaxonomyTermEmptyFormView__maybe_unused(array $uuids)
    // Has to be public to be called from template!
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('url_attach_taxonomy_term_leaf', $uuids))
            ->getForm()->createView()
        ;
    }
// <form action={{ path('url_attach_taxonomy_term_leaf', { 'url_uuid': uRL.uuid, 'taxo_uuid': term.uuid}) }}  method="post"><input type="submit" value={{ term.term }}></form>
}

class FormGenerator
{
    function __construct($formBuilder, $router)
    {
        $this->formBuilder = $formBuilder;
        $this->router = $router;
    }


    public function create(array $uuids)
    {
        return $this->formBuilder
            ->setAction($this->router->generate('url_attach_taxonomy_term_leaf', $uuids))
            ->getForm()
            ->createView()
        ;
    }   
}

class CallableObject__unused // To call a function from Twig?
{
    function __construct(callable $c)
    {
        $this->callableObject = $c;
    }


    public function __invoke(...$params)
    {
        $this->callableObject(...$params);
    }
}

// https://github.com/umpirsky/twig-php-function