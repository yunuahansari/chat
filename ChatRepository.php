<?php

namespace App\Repositories\Api;

/**
 * Description: this repository is used only for chat related operations 
 * Author : Codiant- A Yash Technologies Company 
 * Date :10 march 2019
 * 
 */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Message;
use JWTAuth;
use App\Models\Connection;

Class ChatRepository {

    public function __construct(Message $message, Connection $connection) {
        $this->message = $message;
        $this->connection = $connection;
    }

    /**
     * Save message
     * @param type $request(obj)
     * @return boolean
     */
    public function saveMessage($request) {

        $connection = $this->connection->where(function ($query) use ($request) {
                    $query->where('from_id', '=', $request->from_id)
                            ->where('to_id', '=', $request->to_id);
                })->orWhere(function ($query) use ($request) {
                    $query->where('to_id', '=', $request->from_id)
                            ->where('from_id', '=', $request->to_id);
                })->first();
        if (!$connection) {
            $connection = new $this->connection();
            $connection->from_id = $request->from_id;
            $connection->to_id = $request->to_id;
            $connection->save();
        }
        $chat = new $this->message();
        $chat->from_id = $request->from_id;
        $chat->to_id = $request->to_id;
        $chat->message = $request->message;
        $chat->connection_id = $connection->id;
        if ($chat->save()) {
            return true;
        }return false;
    }

    /**
     * Get inbox list
     * @param type $request
     * @return type
     */
    public function getInbox($request) {

        $user = JWTAuth::toUser($request->header('access_token')); // to get login user detail
        $chatInbox = $this->message->select('messages.from_id', 'messages.to_id', 'messages.message', 'messages.created_at')->where('to_id', $user->id)
                ->orWhere('from_id', $user->id)
                ->join(DB::raw('(Select max(id) as id from messages where to_id =' . $user->id . ' or ' . 'from_id =' . $user->id . ' group by connection_id) LatestMessage'), function($join) {
                    $join->on('message s.id', '=', 'LatestMessage.id');
                })
                ->paginate(10);
        if (count($chatInbox) > 0) {
            foreach ($chatInbox as $chat) {
                $user_id = ($user->id ==$chat['from_id'])?$chat['to_id']:$chat['from_id'];
                $fromUser = \App\User::where(['id' => $user_id])->first();
                $chat->from_user_image = checkUserImage($fromUser->profile_image, 'users'); // to get opposite user  profile image
                $chat->from_user_first_name = $fromUser['first_name'];
                $chat->from_user_last_name = $fromUser['last_name'];
                $chat->unread_count = getUnreadCount($chat['to_id']); // to get unread message count with opposite user
                $chat->to_user_id = $user_id;
            }
        }
        return $chatInbox;
    }

    /**
     * Get getChatList conversation between two user
     * @param type $request(OBJ)
     * @return type OBJ
     */
    public function getChatList($request) {

        $user = JWTAuth::toUser($request->header('access_token'));
        $fromId = $user->id;
        $toId = $request->to_id;
        $chatLists = $this->message->where(function ($query) use ($fromId, $toId) {
                    $query->where('from_id', '=', $fromId)
                            ->where('to_id', '=', $toId);
                })->orWhere(function ($query) use ($fromId, $toId) {
                    $query->where('to_id', '=', $fromId)
                            ->where('from_id', '=', $toId);
                })->orderBy('id','desc')->paginate(10);
        if (count($chatLists) > 0) {
            $affected = DB::table('messages')->where('to_id', '=', $user->id)->update(array('is_read' => 1));
            foreach ($chatLists as $chat) {
                $fromUserId = ($user->id ==$chat['from_id'])?$chat['from_id']:$chat['to_id'];
                $fromUser = \App\User::where(['id' => $fromUserId])->first();
                $chat->from_user_image = checkUserImage($fromUser->profile_image, 'users'); // image who posted message
                $chat->from_user_first_name = $fromUser['first_name'];
                $chat->from_user_last_name = $fromUser['last_name'];
                $toUserId = ($user->id ==$chat['from_id'])?$chat['to_id']:$chat['from_id'];
                $toUser = \App\User::where(['id' => $toUserId])->first();
                $chat->to_user_image = checkUserImage($toUser->profile_image, 'users'); // image who recieve message
                $chat->to_user_first_name = $toUser['first_name'];
                $chat->to_user_last_name = $toUser['last_name'];
            }
        }
        return $chatLists;
    }

    /**
     * delete Chat conversation between two person
     * @param type $request(OBJ)
     * @return boolean
     */
    public function deleteChat($request) {

        $user = JWTAuth::toUser($request->header('access_token'));
        $fromId = $user->id;
        $toId = $request->to_id;
        $chatLists = $this->message->where(function ($query) use ($fromId, $toId) {
                    $query->where('from_id', '=', $fromId)
                            ->where('to_id', '=', $toId);
                })->orWhere(function ($query) use ($fromId, $toId) {
                    $query->where('to_id', '=', $fromId)
                            ->where('from_id', '=', $toId);
                })->delete();
        if ($chatLists) {
            return true;
        }
        return false;
    }

}
