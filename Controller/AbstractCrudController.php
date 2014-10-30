<?php

namespace Mero\BaseBundle\Controller;

/**
 * Classe abstrata para criação de CRUD simples
 *
 * @package Mero\BaseBundle\Controller
 * @author Rafael Mello <merorafael@gmail.com>
 * @copyright Copyright (c) 2014 - Rafael Mello
 * @license https://github.com/merorafael/MeroBaseBundle/blob/master/LICENSE BSD license
 */
abstract class AbstractCrudController extends \Symfony\Bundle\FrameworkBundle\Controller\Controller
{
    
    /**
     * @var Doctrine\ORM\EntityManager Entity manager do Doctrine
     */
    protected $em;
    
    /**
     * @var string Nome da rota para indexAction
     */
    const indexRoute = 'index';
    
    /**
     * @var string Nome da rota para addAction
     */
    const addRoute = 'add';
    
    /**
     * @var string Nome da rota para editAction
     */
    const editRoute = 'edit';
    
    /**
     * @var string Nome da rota para redirecionamento pós-inserção.
     */
    const createdRoute = null;
    
    /**
     * @var string Nome da rota para redirecionamento pós-atualização.
     */
    const updatedRoute = null;
    
    /**
     * @var string Nome da rota para redirecionamento pós-exclusão.
     */
    const removedRoute = null;
    
    /**
     * Namespace referente a classe da entidade.
     *
     * @return string
     */
    abstract protected function getEntityNamespace();
    
    /**
     * Classe referente a entidade.
     * 
     * @return string
     */
    abstract protected function getEntityName();
    
    /**
     * Nome referente ao bundle.
     * 
     * @return string
     */
    abstract protected function getBundleName();
    
    /**
     * Nome referente ao tipo de formulario
     * 
     * @return string
     */
    abstract protected function getType();
    
    /**
     * Retorna gerenciador de entidades(Entity Manager) do Doctrine.
     * 
     * @return \Doctrine\ORM\EntityManager Entity Manager do Doctrine
     */
    public function getDoctrineManager()
    {
        return $this->getDoctrine()->getManager();
    }
    
    /**
     * Método utilizado em classes extendidas para alterar Query Builder padrão
     * utilizado pelo método indexAction.
     * 
     * @see http://doctrine-orm.readthedocs.org/en/latest/reference/query-builder.html Documentação do Query Builder pelo Doctrine
     * @see \Mero\BaseBundle\Controller::indexAction() Action referente a index do CRUD
     * 
     * @param \Doctrine\ORM\QueryBuilder $entity_q Entrada do Query Builder em indexAction
     * @return \Doctrine\ORM\QueryBuilder Query Builder processado pelo método
     */
    protected function indexQueryBuilder(\Doctrine\ORM\QueryBuilder $entity_q)
    {
        return $entity_q;
    }
    
    /**
     * Retorna campo padrão utilizado para ordenação de dados.
     * 
     * @return string Campo da entity
     */
    protected function defaultSort()
    {
        return 'created';
    }

    /**
     * Método utilizado em classes extendidas para manipular dados da entidade que não 
     * correspondem a um CRUD simples.
     * 
     * @param \Mero\BaseBundle\Entity\AbstractEntity $entity Entity referente ao CRUD
     */
    protected function dataManager(\Mero\BaseBundle\Entity\AbstractEntity $entity) 
    {
        return $entity;
    }
    
    /**
     * Cria o formulário de inserção de dados baseado na entidade informada.
     * 
     * @param \Mero\BaseBundle\Entity\AbstractEntity $entity Entity referente ao CRUD
     * @return \Symfony\Component\Form\Form Formulário do Symfony
     */
    protected function getInsertForm(\Mero\BaseBundle\Entity\AbstractEntity $entity)
    {
        $form = $this->createForm($this->getType(), $entity, array(
            'action' => $this->generateUrl(static::addRoute),
            'method' => 'POST'
        ));
        $form->add('submit', 'submit');
        return $form;
    }
    
    /**
     * Cria o formulário de alteração de dados baseado na entidade informada.
     * 
     * @param \Mero\BaseBundle\Entity\AbstractEntity $entity Entity referente ao CRUD
     * @return \Symfony\Component\Form\Form Formulário do Symfony
     */
    protected function getUpdateForm(\Mero\BaseBundle\Entity\AbstractEntity $entity)
    {
        $form = $this->createForm($this->getType(), $entity, array(
            'action' => $this->generateUrl(static::editRoute, array(
                'id' => $entity->getId()
            )),
            'method' => 'PUT'
        ));
        $form->add('submit', 'submit');
        return $form;
    }
    
    /**
     * Action de listagem dos registros
     * 
     * Os dados exibidos são controlados com parâmetros $_GET
     * page - Qual página está sendo exibida(padrão 0);
     * limit - Quantidade de registros por página(padrão 10);
     * sort - Campo a ser utilizado para ordenação(padrão "created")
     * order - Como será ordernado o campo sort(padrão DESC)
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id Utilizado para editar um registro na indexAction caso informado
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(\Symfony\Component\HttpFoundation\Request $request, $id = null)
    {
        $page = $request->query->get('page') ? $request->query->get('page') : 1;
        $limit = $request->query->get('limit') ? $request->query->get('limit') : 10;
        $sort = $request->query->get('sort') ? null : $this->defaultSort();
        
        $em = $this->getDoctrine()->getManager();
        $entity_q = $em->createQueryBuilder()
            ->select('e')
            ->from($this->getBundleName().":".$this->getEntityName(), 'e')
        ;
        if (!$request->query->get('sort')) {
            $entity_q->orderBy("e.{$this->defaultSort()}", "DESC");
        }
        
        $entity_q = $this->indexQueryBuilder($entity_q);
        
        //Recurso dependente do KnpPaginatorBundle
        $entities = $this->get('knp_paginator')->paginate($entity_q->getQuery(), $page, $limit);
        
        //Adiciona formulário de CRUD(adicionar ou editar de acordo com a identificação informada).
        $crud = !empty($id) ? $this->editData($request, $id) : $this->addData($request);
        if (!is_array($crud)) {
            return $crud;
        }
        
        return $this->render($this->getBundleName().":".$this->getEntityName().":index.html.twig", array_merge(
            $crud,
            array(
                'entities' => $entities
            )
        ));
    }
    
    /**
     * Action para exibir detalhes de registro especifico
     * 
     * @param integer $id Identificação do registro
     */
    public function detailsAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository($this->getBundleName().":".$this->getEntityName())->find($id);
        if (!$entity) {
            $this->get('session')
                ->getFlashBag()
                ->add('danger', 'Registro não encontrado.');
            return $this->redirect($this->generateUrl(static::indexRoute));
        }
        return $this->render($this->getBundleName().":".$this->getEntityName().":details.html.twig", array(
            'entity' => $entity
        ));
    }
    
    /**
     * Método responsável por adicionar novos registros
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array
     */
    private function addData(\Symfony\Component\HttpFoundation\Request $request)
    {
        $entity_class = $this->getEntityNamespace()."\\".$this->getEntityName();
        if (!class_exists($entity_class)) {
            throw $this->createNotFoundException('Entity not found');
        }
        $entity = new $entity_class();
        $form = $this->getInsertForm($entity);
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $entity = $this->dataManager($entity);
                $em = $this->getDoctrine()->getManager();
                $em->persist($entity);
                $em->flush();
                $this->get('session')
                    ->getFlashBag()
                    ->add('success', 'Operação realizada com sucesso.');
                return $this->redirect($this->generateUrl(is_null(static::createdRoute) ? static::indexRoute : static::createdRoute));
            } else {
                $this->get('session')
                    ->getFlashBag()
                    ->add('danger', 'Falha ao realizar operação.');
            }
        }
        return array(
            'entity' => $entity,
            'form' => $form->createView()
        );
    }
    
    /**
     * Action para adicionar novos registros
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function addAction(\Symfony\Component\HttpFoundation\Request $request)
    {
        $crud  = $this->addData($request);
        if (!is_array($crud)) {
            return $crud;
        }
        return $this->render($this->getBundleName().":".$this->getEntityName().":add.html.twig", $crud);
    }
    
    /**
     * Método responsável por alterar registros
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id Identificação do registro
     * @return array
     */
    private function editData(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository($this->getBundleName().":".$this->getEntityName())->find($id);
        if (!$entity) {
            $this->get('session')
            ->getFlashBag()
            ->add('danger', 'Registro não encontrado.');
            return $this->redirect($this->generateUrl(is_null(static::updatedRoute) ? static::indexRoute : static::updatedRoute));
        }
        $form = $this->getUpdateForm($entity);
        if ($request->isMethod('PUT')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $entity = $this->dataManager($entity);
                $em->persist($entity);
                $em->flush();
                $this->get('session')
                ->getFlashBag()
                ->add('success', 'Operação realizada com sucesso.');
                return $this->redirect($this->generateUrl(is_null(static::updatedRoute) ? static::indexRoute : static::updatedRoute));
            } else {
                $this->get('session')
                ->getFlashBag()
                ->add('danger', 'Falha ao realizar operação.');
            }
        }
        return array(
            'entity' => $entity,
            'form' => $form->createView()
        );
    }
    
    /**
     * Método action responsável por alteração de registros
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id Identificação do registro
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        $crud = $this->editData($request, $id);
        if (!is_array($crud)) {
            return $crud;
        }
        return $this->render($this->getBundleName().":".$this->getEntityName().":edit.html.twig", $crud);
    }
    
    /**
     * Método action responsável por remoção de registros
     * 
     * @param integer $id Identificação do registro
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository($this->getBundleName().":".$this->getEntityName())->find($id);
        if (!$entity) {
            $this->get('session')
                ->getFlashBag()
                ->add('danger', 'Registro não encontrado.');
        } else {
            $em->remove($entity);
            $em->flush();
            $this->get('session')
                ->getFlashBag()
                ->add('success', 'Operação realizada com sucesso.');
        }
        return $this->redirect($this->generateUrl(is_null(static::removedRoute) ? static::indexRoute : static::removedRoute));
    }
}