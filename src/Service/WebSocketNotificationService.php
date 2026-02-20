<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServer;

class WebSocketNotificationService implements MessageComponentInterface
{
    private \SplObjectStorage $connections;
    private array $userConnections = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private LoggerInterface $logger,
    ) {
        $this->connections = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->connections->attach($conn);
        $this->logger->info("New WebSocket connection established: {$conn->resourceId}");
        
        // Send connection confirmation
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'WebSocket connection established',
            'timestamp' => time()
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data) {
                $from->send(json_encode(['error' => 'Invalid JSON']));
                return;
            }

            switch ($data['action'] ?? '') {
                case 'authenticate':
                    $this->authenticateUser($from, $data);
                    break;
                    
                case 'subscribe':
                    $this->subscribeToNotifications($from, $data);
                    break;
                    
                case 'unsubscribe':
                    $this->unsubscribeFromNotifications($from, $data);
                    break;
                    
                case 'get_unread_count':
                    $this->sendUnreadCount($from, $data);
                    break;
                    
                default:
                    $from->send(json_encode(['error' => 'Unknown action']));
            }
        } catch (\Exception $e) {
            $this->logger->error("WebSocket message error: " . $e->getMessage());
            $from->send(json_encode(['error' => 'Server error']));
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        
        // Remove from user connections
        foreach ($this->userConnections as $userId => $userConns) {
            if (($key = array_search($conn, $userConns, true)) !== false) {
                unset($this->userConnections[$userId][$key]);
                if (empty($this->userConnections[$userId])) {
                    unset($this->userConnections[$userId]);
                }
                break;
            }
        }
        
        $this->logger->info("WebSocket connection closed: {$conn->resourceId}");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->logger->error("WebSocket error: " . $e->getMessage());
        $conn->close();
    }

    private function authenticateUser(ConnectionInterface $conn, array $data): void
    {
        $userId = $data['user_id'] ?? null;
        $token = $data['token'] ?? null;
        
        if (!$userId || !$token) {
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Missing user_id or token'
            ]));
            return;
        }

        // Here you would validate the token against your authentication system
        // For now, we'll accept any valid user ID
        try {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $conn->send(json_encode([
                    'type' => 'auth_error',
                    'message' => 'User not found'
                ]));
                return;
            }

            // Store user connection
            if (!isset($this->userConnections[$userId])) {
                $this->userConnections[$userId] = [];
            }
            $this->userConnections[$userId][] = $conn;

            $conn->send(json_encode([
                'type' => 'authenticated',
                'user_id' => $userId,
                'message' => 'Authentication successful'
            ]));

            $this->logger->info("User {$userId} authenticated via WebSocket");
        } catch (\Exception $e) {
            $this->logger->error("Authentication error: " . $e->getMessage());
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Authentication failed'
            ]));
        }
    }

    private function subscribeToNotifications(ConnectionInterface $conn, array $data): void
    {
        $userId = $data['user_id'] ?? null;
        $channels = $data['channels'] ?? ['in_app'];
        
        if (!$userId) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Missing user_id'
            ]));
            return;
        }

        // Store subscription info in connection
        $conn->userId = $userId;
        $conn->channels = $channels;

        $conn->send(json_encode([
            'type' => 'subscribed',
            'channels' => $channels,
            'message' => 'Successfully subscribed to notifications'
        ]));
    }

    private function unsubscribeFromNotifications(ConnectionInterface $conn, array $data): void
    {
        $userId = $data['user_id'] ?? null;
        
        if (!$userId) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Missing user_id'
            ]));
            return;
        }

        // Clear subscription
        unset($conn->userId);
        unset($conn->channels);

        $conn->send(json_encode([
            'type' => 'unsubscribed',
            'message' => 'Successfully unsubscribed from notifications'
        ]));
    }

    private function sendUnreadCount(ConnectionInterface $conn, array $data): void
    {
        $userId = $data['user_id'] ?? null;
        
        if (!$userId) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Missing user_id'
            ]));
            return;
        }

        try {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $conn->send(json_encode([
                    'type' => 'error',
                    'message' => 'User not found'
                ]));
                return;
            }

            $unreadCount = $this->notificationRepository->count([
                'user' => $user,
                'isRead' => false
            ]);

            $conn->send(json_encode([
                'type' => 'unread_count',
                'count' => $unreadCount,
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            $this->logger->error("Error getting unread count: " . $e->getMessage());
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Failed to get unread count'
            ]));
        }
    }

    /**
     * Send notification to specific user
     */
    public function sendToUser(int $userId, array $notificationData): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        $message = json_encode([
            'type' => 'notification',
            'data' => $notificationData,
            'timestamp' => time()
        ]);

        foreach ($this->userConnections[$userId] as $conn) {
            try {
                $conn->send($message);
            } catch (\Exception $e) {
                $this->logger->error("Failed to send notification to connection: " . $e->getMessage());
                // Remove broken connection
                $this->connections->detach($conn);
                $key = array_search($conn, $this->userConnections[$userId], true);
                if ($key !== false) {
                    unset($this->userConnections[$userId][$key]);
                }
            }
        }

        // Clean up empty user connections
        if (empty($this->userConnections[$userId])) {
            unset($this->userConnections[$userId]);
        }
    }

    /**
     * Send notification to all connected users
     */
    public function broadcast(array $notificationData): void
    {
        $message = json_encode([
            'type' => 'broadcast',
            'data' => $notificationData,
            'timestamp' => time()
        ]);

        foreach ($this->connections as $conn) {
            try {
                $conn->send($message);
            } catch (\Exception $e) {
                $this->logger->error("Failed to broadcast to connection: " . $e->getMessage());
                $this->connections->detach($conn);
            }
        }
    }

    /**
     * Send notification entity to user
     */
    public function sendNotificationEntity(Notification $notification): void
    {
        $userId = $notification->getUser()->getId();
        
        $notificationData = [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'type' => $notification->getType(),
            'channel' => $notification->getChannel(),
            'created_at' => $notification->getCreatedAt()->format('c'),
            'is_read' => $notification->isRead(),
        ];

        if ($notification->getTask()) {
            $notificationData['task_id'] = $notification->getTask()->getId();
            $notificationData['task_title'] = $notification->getTask()->getTitle();
        }

        $this->sendToUser($userId, $notificationData);
    }

    /**
     * Get connection count
     */
    public function getConnectionCount(): int
    {
        return $this->connections->count();
    }

    /**
     * Get connected users count
     */
    public function getConnectedUsersCount(): int
    {
        return count($this->userConnections);
    }
}