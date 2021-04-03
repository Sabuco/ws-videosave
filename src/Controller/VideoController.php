<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
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
    
    public function create(Request $request, JwtAuth $jwt_auth) {
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
                }
            }
        }
        
        return $this->resjson($data);
    }
}
