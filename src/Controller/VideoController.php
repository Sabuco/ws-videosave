<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use Knp\Component\Pager\PaginatorInterface;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class VideoController extends AbstractController
{
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }
    
    private function resjson($data)
    {
        // Serializar datos con servicio de serializer
        $json = $this->get('serializer')->serialize($data, 'json');
        
        // Response con httpfoundation
        $response = new Response();
        
        // Asignar contenido a la respuesta
        $response->setContent($json);
        
        // Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');
        
        // Devolver la respuesta
        return $response;
    }
    
    public function create(Request $request, JwtAuth $jwt_auth, $id = null) {
        $token = $request->headers->get('Authorization', null);
        
        $authCheck = $jwt_auth->checkToken($token);
        
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El video no ha podido crearse'
        ];
        
        if($authCheck) {
            $json = $request->get('json', null);
            $params = json_decode($json);
            
            $identity = $jwt_auth->checkToken($token, true);
            
            if(!empty($json)) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;
                
                if(!empty($user_id) && !empty($title)) {
                    $entity_manager = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id' => $user_id
                    ]);
                    
                    if($id == null) {
                    
                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');

                        $createdAt = new \Datetime('now');
                        $updatedAt = new \Datetime('now');
                        $video->setCreatedAt($createdAt);
                        $video->setUpdatedAt($updatedAt);

                        $entity_manager->persist($video);
                        $entity_manager->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El video se ha guardado correctamente',
                            'video' => $video
                        ];
                    } else {
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id' => $id,
                            'user' => $identity->sub
                        ]);
                        
                        if($video && is_object($video)) {
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);

                            $updatedAt = new \Datetime('now');
                            $video->setUpdatedAt($updatedAt);
                            
                            $entity_manager->persist($video);
                            $entity_manager->flush();
                            
                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El video se ha actualizado correctamente',
                                'video' => $video
                            ];
                        }
                    }
                }
            }
        }
        
        return $this->resjson($data);
    }
    
    public function getAll(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator) {
        $token = $request->headers->get('Authorization');
        
        $authCheck = $jwt_auth->checkToken($token);
        
        if($authCheck) {
            $identity = $jwt_auth->checkToken($token, true);
            
            $entity_manager = $this->getDoctrine()->getManager();
            
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $entity_manager->createQuery($dql);
            
            $page = $request->query->get('page', 1);
            $items_per_page = 5;
            
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();
            
            $data = [
                'status' => 'success',
                'code' => 200,
                'totalItemsCount' => $total,
                'currentPage' => $page,
                'itemsPerPage' => $items_per_page,
                'totalPages' => ceil($total / $items_per_page),
                'videos' => $pagination,
                'userId' => $identity->sub
            ]; 
        } else {
            $data = [
                'status' => 'error',
                'code' => 404,
                'message' => 'No se pueden listar los videos en este momento'
            ]; 
        }

        return $this->resjson($data);
    }
    
    public function getById(Request $request, JwtAuth $jwt_auth, $id = null) {
        $token = $request->headers->get('Authorization');
        
        $authCheck = $jwt_auth->checkToken($token);
        
        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado'
        ]; 
        
        if($authCheck) {
            $identity = $jwt_auth->checkToken($token, true);
            
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            
            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'data' => $video
                ]; 
            }
        }

        return $this->resjson($data);
    }
    
    public function deleteById(Request $request, JwtAuth $jwt_auth, $id = null) {
        $token = $request->headers->get('Authorization');
        
        $authCheck = $jwt_auth->checkToken($token);
        
        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado'
        ]; 
        
        if($authCheck) {
            $identity = $jwt_auth->checkToken($token, true);
            
            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            
            $video = $doctrine->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            
            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $em->remove($video);
                $em->flush();
                
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'data' => $video
                ]; 
            }
        }
        
        return $this->resjson($data);
    }
}
