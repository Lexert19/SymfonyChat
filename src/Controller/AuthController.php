<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\UserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/auth', name: 'auth.')]
class AuthController extends AbstractController
{
    private $userProvider;
    private $jwtEncoder;
    private $passwordHasher;
    private $tokenManager;

    public function __construct(
        UserProvider $userProvider, 
        JWTEncoderInterface $jwtEncoder, 
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $tokenManager)
    {
        $this->userProvider = $userProvider;
        $this->jwtEncoder = $jwtEncoder;
        $this->passwordHasher = $passwordHasher;
        $this->tokenManager = $tokenManager;
    }

    #[Route('/login', name: 'login')]
    public function login(Request $request): JsonResponse
    {
        $credentials = json_decode($request->getContent(), true);

        try{
            /** @var User $user */
            $user = $this->userProvider->loadUserByIdentifier($credentials['email']);
        }catch(Exception $e){
            return $this->json(['message' => 'User not found'], 401);
        }


        if(!$this->isPasswordValid($credentials['password'], $user)){
            return $this->json(['message' => 'Invalid password'], 401);
        }

        $token = $this->tokenManager->create($user);
        #$token = $this->jwtEncoder->encode(['email' => $user->getEmail()]);

        return new JsonResponse(['token' => $token]);
    }



    #[Route('/register', name: 'register')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'];
        $email = $data['email'];
        $plainPassword = $data['password'];

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['message' => 'Email already registered'], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);


        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User registered successfully'], 201);
    }


    private function isPasswordValid(string $plainPassword, PasswordAuthenticatedUserInterface $user): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
    // private function isPasswordValid(string $plainPassword, string $hashedPassword){
    //     $plainPassword = $passwordHasher->hashPassword($user, $plainPassword);
    //     return $plainPassword === $hashedPassword;
    // }
}
