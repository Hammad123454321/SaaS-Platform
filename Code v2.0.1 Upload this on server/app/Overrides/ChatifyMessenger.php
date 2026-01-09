<?php

namespace Chatify;

use App\Models\ChMessage as Message;
use App\Models\ChFavorite as Favorite;
use Illuminate\Support\Facades\Storage;
use Pusher\Pusher;
use Illuminate\Support\Facades\Auth;
use Exception;

class ChatifyMessenger
{
    public $pusher;

    /**
     * Get max file's upload size in MB.
     *
     * @return int
     */
    public function getMaxUploadSize()
    {
        return config('chatify.attachments.max_upload_size') * 1048576;
    }

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('chatify.pusher.key'),
            config('chatify.pusher.secret'),
            config('chatify.pusher.app_id'),
            config('chatify.pusher.options'),
        );
    }

    public function conciseDiffForHumans($date)
    {
        $diff = $date->diffForHumans();

        // Replace long strings with shorter versions
        $replacements = [
            ' seconds ago' => 's ago',
            ' second ago' => 's ago',
            ' minutes ago' => 'm ago',
            ' minute ago' => 'm ago',
            ' hours ago' => 'h ago',
            ' hour ago' => 'h ago',
            ' days ago' => 'd ago',
            ' day ago' => 'd ago',
            ' weeks ago' => 'w ago',
            ' week ago' => 'w ago',
            ' months ago' => 'mo ago',
            ' month ago' => 'mo ago',
            ' years ago' => 'y ago',
            ' year ago' => 'y ago',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $diff);
    }

    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedImages()
    {
        return config('chatify.attachments.allowed_images');
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles()
    {
        return config('chatify.attachments.allowed_files');
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public function getMessengerColors()
    {
        return config('chatify.colors');
    }

    /**
     * Returns a fallback primary color.
     *
     * @return array
     */
    public function getFallbackColor()
    {
        $colors = $this->getMessengerColors();
        return count($colors) > 0 ? $colors[0] : '#000000';
    }

    /**
     * Trigger an event using Pusher
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function push($channel, $event, $data)
    {
        return $this->pusher->trigger($channel, $event, $data);
    }

    /**
     * Authentication for pusher
     *
     * @param User $requestUser
     * @param User $authUser
     * @param string $channelName
     * @param string $socket_id
     * @param array $data
     * @return void
     */
    public function pusherAuth($requestUser, $authUser, $channelName, $socket_id)
    {
        // Auth data
        $authData = json_encode([
            'user_id' => $authUser->id,
            'user_info' => [
                'name' => $authUser->name
            ]
        ]);
        // check if user authenticated
        if (Auth::check()) {
            if ($requestUser->id == $authUser->id) {
                return $this->pusher->socket_auth(
                    $channelName,
                    $socket_id,
                    $authData
                );
            }
            // if not authorized
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // if not authenticated
        return response()->json(['message' => 'Not authenticated'], 403);
    }

    /**
     * Fetch & parse message and return the message card
     * view as a response.
     *
     * @param Message $prefetchedMessage
     * @param int $id
     * @return array
     */
    public function parseMessage($prefetchedMessage = null, $id = null)
    {
        $msg = null;
        $attachment = null;
        $attachment_type = null;
        $attachment_title = null;
        if (!!$prefetchedMessage) {
            $msg = $prefetchedMessage;
        } else {
            $msg = Message::where('id', $id)->first();
            if (!$msg) {
                return [];
            }
        }
        if (isset($msg->attachment)) {
            $attachmentOBJ = json_decode($msg->attachment);
            $attachment = $attachmentOBJ->new_name;
            $attachment_title = htmlentities(trim($attachmentOBJ->old_name), ENT_QUOTES, 'UTF-8');
            $ext = pathinfo($attachment, PATHINFO_EXTENSION);
            $attachment_type = in_array($ext, $this->getAllowedImages()) ? 'image' : 'file';
        }
        return [
            'id' => $msg->id,
            'from_id' => $msg->from_id,
            'to_id' => $msg->to_id,
            'message' => $msg->body,
            'attachment' => (object) [
                'file' => $attachment,
                'title' => $attachment_title,
                'type' => $attachment_type
            ],
            'timeAgo' => $this->conciseDiffForHumans($msg->created_at),
            'created_at' => $msg->created_at->toIso8601String(),
            'isSender' => ($msg->from_id == Auth::user()->id),
            'seen' => $msg->seen,
        ];
    }

    /**
     * Return a message card with the given data.
     *
     * @param Message $data
     * @param boolean $isSender
     * @return string
     */
    public function messageCard($data, $renderDefaultCard = false)
    {
        if (!$data) {
            return '';
        }
        if ($renderDefaultCard) {
            $data['isSender'] =  false;
        }
        return view('Chatify::layouts.messageCard', $data)->render();
    }

    /**
     * Default fetch messages query between a Sender and Receiver.
     *
     * @param int $user_id
     * @return Message|\Illuminate\Database\Eloquent\Builder
     */
    public function fetchMessagesQuery($user_id, $type = null, $type_id = null)
    {
        $workspace_id = getWorkspaceId();
        $current_user_id = Auth::user()->id;

        $query = Message::where(function ($q) use ($user_id, $workspace_id, $current_user_id) {
            $q->where(function ($subQuery) use ($user_id, $workspace_id, $current_user_id) {
                $subQuery->where('from_id', $current_user_id)
                    ->where('to_id', $user_id)
                    ->where('workspace_id', $workspace_id);
            })->orWhere(function ($subQuery) use ($user_id, $workspace_id, $current_user_id) {
                $subQuery->where('from_id', $user_id)
                    ->where('to_id', $current_user_id)
                    ->where('workspace_id', $workspace_id);
            });
        });

        if ($type !== null && $type_id !== null) {
            $query->where('type', $type)
                ->where('type_id', $type_id);
        } else {
            $query->where('type', null)
                ->where('type_id', null);
        }

        return $query;
    }


    /**
     * create a new message to database
     *
     * @param array $data
     * @return Message
     */
    public function newMessage($data)
    {
        $message = new Message();
        $message->from_id = $data['from_id'];
        $message->to_id = $data['to_id'];
        $message->workspace_id = $data['workspace_id'];
        $message->type = $data['type'] ?? null;
        $message->type_id = $data['type_id'] ?? null;
        $message->body = $data['body'];
        $message->attachment = $data['attachment'];
        $message->save();
        return $message;
    }

    /**
     * Make messages between the sender [Auth user] and
     * the receiver [User id] as seen.
     *
     * @param int $user_id
     * @return bool
     */
    public function makeSeen($user_id)
    {
        $where = ['from_id' => $user_id, 'seen' => 0, 'to_id' => Auth::user()->id, 'workspace_id' => getWorkspaceId()];
        Message::Where($where)
            ->update(['seen' => 1]);
        return 1;
    }

    /**
     * Get last message for a specific user
     *
     * @param int $user_id
     * @return Message|Collection|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getLastMessageQuery($user_id, $type = null, $type_id = null)
    {
        return $this->fetchMessagesQuery($user_id, $type, $type_id)->latest()->first();
    }

    /**
     * Count Unseen messages
     *
     * @param int $user_id
     * @return Collection
     */
    public function countUnseenMessages($user_id, $type = null, $type_id = null)
    {
        $where = [
            'from_id' => $user_id,
            'seen' => 0,
            'to_id' => Auth::user()->id,
            'workspace_id' => getWorkspaceId()
        ];

        // Add conditions for type and type_id if they are not null
        if ($type !== null && $type_id !== null) {
            $where['type'] = $type;
            $where['type_id'] = $type_id;
        }

        return Message::where($where)->count();
    }

    public function totalUnseenMessages()
    {
        return Message::where('seen', 0)->where('from_id', '!=', getAuthenticatedUser()->id)->where('to_id', getAuthenticatedUser()->id)->where('workspace_id', getWorkspaceId())->count();
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param int $messenger_id
     * @param Collection $user
     * @return string
     */
    public function getContactItem($user, $type = null, $type_id = null)
    {
        try {
            // get last message
            $lastMessage = $this->getLastMessageQuery($user->id, $type, $type_id);
            // Get Unseen messages counter
            $unseenCounter = $this->countUnseenMessages($user->id, $type, $type_id);
            if ($lastMessage) {
                $lastMessage->created_at = $lastMessage->created_at->toIso8601String();
                $lastMessage->timeAgo = $this->conciseDiffForHumans($lastMessage->created_at);
            }
            return view('Chatify::layouts.listItem', [
                'get' => 'users',
                'user' => $this->getUserWithAvatar($user),
                'lastMessage' => $lastMessage,
                'unseenCounter' => $unseenCounter,
            ])->render();
        } catch (\Throwable $th) {
            throw new Exception($th->getMessage());
        }
    }

    /**
     * Get user with avatar (formatted).
     *
     * @param Collection $user
     * @return Collection
     */
    public function getUserWithAvatar($user)
    {
        if ($user->avatar == 'avatar.png' && config('chatify.gravatar.enabled')) {
            $imageSize = config('chatify.gravatar.image_size');
            $imageset = config('chatify.gravatar.imageset');
            $user->avatar = asset('storage/' . $user->photo);
            // 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=' . $imageSize . '&d=' . $imageset;
        } else {
            $user->avatar = self::getUserAvatarUrl($user->avatar);
        }
        return $user;
    }

    /**
     * Check if a user in the favorite list
     *
     * @param int $user_id
     * @return boolean
     */
    public function inFavorite($user_id, $typeId = null, $type = null)
    {
        $where = [
            'favorite_id' => $user_id,
            'user_id' => Auth::user()->id,
            'workspace_id' => getWorkspaceId()
        ];

        if ($typeId !== null && $type !== null) {
            $where['type_id'] = $typeId;
            $where['type'] = $type;
        } else {
            $where['type_id'] = null;
            $where['type'] = null;
        }

        return Favorite::where($where)->count() > 0
            ? true : false;
    }

    /**
     * Make user in favorite list
     *
     * @param int $user_id
     * @param int $star
     * @return boolean
     */
    public function makeInFavorite($user_id, $action, $typeId = null, $type = null)
    {
        if ($action > 0) {
            // Star
            $star = new Favorite();
            $star->user_id = Auth::user()->id;
            $star->workspace_id = getWorkspaceId();
            $star->favorite_id = $user_id;
            if ($typeId !== null && $type !== null) {
                $star->type_id = $typeId;
                $star->type = $type;
            }
            $star->save();
            return $star ? true : false;
        } else {
            // UnStar
            $query = Favorite::where('user_id', Auth::user()->id)
                ->where('favorite_id', $user_id)
                ->where('workspace_id', getWorkspaceId());
            if ($typeId !== null && $type !== null) {
                $query->where('type_id', $typeId);
                $query->where('type', $type);
            } else {
                $query->where('type_id', null);
                $query->where('type', null);
            }
            $star = $query->delete();
            return $star ? true : false;
        }
    }

    /**
     * Get shared photos of the conversation
     *
     * @param int $user_id
     * @return array
     */
    public function getSharedPhotos($user_id, $type = null, $type_id = null)
    {
        $images = array(); // Default
        // Get messages
        $msgs = $this->fetchMessagesQuery($user_id, $type, $type_id)->orderBy('created_at', 'DESC');
        if ($msgs->count() > 0) {
            foreach ($msgs->get() as $msg) {
                // If message has attachment
                if ($msg->attachment) {
                    $attachment = json_decode($msg->attachment);
                    // determine the type of the attachment
                    in_array(pathinfo($attachment->new_name, PATHINFO_EXTENSION), $this->getAllowedImages())
                        ? array_push($images, $attachment->new_name) : '';
                }
            }
        }
        return $images;
    }

    /**
     * Delete Conversation
     *
     * @param int $user_id
     * @return boolean
     */
    public function deleteConversation($user_id, $type = null, $typeId = null)
    {
        try {
            foreach ($this->fetchMessagesQuery($user_id, $type, $typeId)->get() as $msg) {
                // delete file attached if exist
                if (isset($msg->attachment)) {
                    $path = config('chatify.attachments.folder') . '/' . json_decode($msg->attachment)->new_name;
                    if (self::storage()->exists($path)) {
                        self::storage()->delete($path);
                    }
                }
                // delete from database
                $msg->delete();
            }
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Delete message by ID
     *
     * @param int $id
     * @return boolean
     */
    public function deleteMessage($id)
    {
        try {
            $msg = Message::where('from_id', auth()->id())->where('id', $id)->firstOrFail();
            if (isset($msg->attachment)) {
                $path = config('chatify.attachments.folder') . '/' . json_decode($msg->attachment)->new_name;
                if (self::storage()->exists($path)) {
                    self::storage()->delete($path);
                }
            }
            $msg->delete();
            return 1;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Return a storage instance with disk name specified in the config.
     *
     */
    public function storage()
    {
        return Storage::disk(config('chatify.storage_disk_name'));
    }

    /**
     * Get user avatar url.
     *
     * @param string $user_avatar_name
     * @return string
     */
    public function getUserAvatarUrl($user_avatar_name)
    {
        return self::storage()->url(config('chatify.user_avatar.folder') . '/' . $user_avatar_name);
    }

    /**
     * Get attachment's url.
     *
     * @param string $attachment_name
     * @return string
     */
    public function getAttachmentUrl($attachment_name)
    {
        return asset('storage/' . config('chatify.attachments.folder') . '/' . $attachment_name);
    }
}
