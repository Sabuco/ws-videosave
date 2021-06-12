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

class UserController extends AbstractController
{
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
    public function index(): Response
    {
        
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);
        
        $users = $user_repo->findAll();
        $videos = $video_repo->findAll();
        $user = $user_repo->find(1);
        /*
        foreach ($users as $user) {
            echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";
            
            foreach ($user->getVideos() as $video) {
                echo "<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
            }
        }
        
        die();
         */
        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];
        return $this->resjson($videos);
    }
    
    public function register(Request $request) 
    {
        $json = $request->get('json', null);
        
        $params = json_decode($json);
        
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado'
        ];
        
        if($json != null) {
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);
            
            if(!empty($email) && count($validate_email) == 0 
               && !empty($password) 
               && !empty($name)
               && !empty($surname)) {
                
                $user = new User();
                
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));
                $user->setUpdatedAt(new \DateTime('now'));
                
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);
                
                $entity_manager = $this->getDoctrine()->getManager();
                $user_repo = $this->getDoctrine()->getRepository(User::class);
                
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));
                
                if(count($isset_user) == 0) {
                    $entity_manager->persist($user);
                    $entity_manager->flush();
                    
                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El usuario se ha creado correctamente'
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El usuario ya existe'
                    ];
                }
            }
        }
        
        return new JsonResponse($data);
    }
    
    public function login(Request $request, JwtAuth $jwt_auth) {
        $json = $request->get('json', null);
        $params = json_decode($json);
        
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha podido identificar'
        ];
        
        if ($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $get_token = (!empty($params->gettoken)) ? $params->gettoken : null;
            
            $validator = Validation::createValidator();
            
            $validate_email = $validator->validate($email, [
               new Email()
            ]);
            
            if(!empty($email) && !empty($password) && count($validate_email) == 0) {
                $pwd = hash('sha256', $password);
                
                if ($get_token) {
                    $signup = $jwt_auth->signup($email, $pwd, $get_token);
                } else {
                    $signup = $jwt_auth->signup($email, $pwd);
                }
                
                return new JsonResponse($signup);
            }
        }
        
        return $this->resjson($data);
    }
    
    public function update(Request $request, JwtAuth $jwt_auth) {
        $token = $request->headers->get('Authorization');
        
        $authCheck = $jwt_auth->checkToken($token);
        
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuario no actualizado'
        ];
        
        if ($authCheck) {
            $entity_manager = $this->getDoctrine()->getManager();
            
            // Datos usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);
            
            $json = $request->get('json', null);
            $params = json_decode($json);
            
            if (!empty($json)) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;

                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);

                if(!empty($email) && count($validate_email) == 0
                   && !empty($name)
                   && !empty($surname)) {
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);
                    
                    $isset_user = $user_repo->findBy([
                        'email' => $email
                    ]);
                    
                    if (count($isset_user) == 0 || $identity->email == $email) {
                        $entity_manager->persist($user);
                        $entity_manager->flush();
                        
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Usuario actualizado',
                            'user' => $user
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Email ya en uso'
                        ];
                    }
                }
            }
        }
        
        return $this->resjson($data);
    }
}
