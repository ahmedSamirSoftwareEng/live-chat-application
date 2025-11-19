<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $user = Auth::user();
        $conversation = $user->conversations()->findOrFail($id);
        $messages = $conversation->messages()->paginate(20);
        return view('messages.index', compact('messages'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => [
                Rule::requiredIf(function () use ($request) {
                    return !$request->user_id;
                }),
                'exists:conversations,id',
                'integer'
            ],
            'user_id' => [
                Rule::requiredIf(function () use ($request) {
                    return !$request->conversation_id;
                }),
                'exists:users,id',
                'integer'
            ]
        ]);
        // $user = Auth::user();
        $user = User::find(1);
        $conversation_id = $request->conversation_id;
        $user_id = $request->user_id;

        DB::beginTransaction();
        try {
            if ($conversation_id) {
                $conversation = $user->conversations()->findOrFail($conversation_id);
            } else {
                $conversation = Conversation::where('type', 'peer')
                    ->whereHas('participants', function ($query) use ($user_id, $user) {
                        $query->join('participants as participants2', 'participants2.conversation_id', '=', 'participants.conversation_id')
                            ->where('participants.user_id', $user_id)
                            ->where('participants2.user_id', $user->id);
                    })->first();
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'user_id' => $user->id,
                        'type' => 'peer'
                    ]);
                    $conversation->participants()->attach(
                        [
                            $user_id => ['joined_at' => now()],
                            $user->id => ['joined_at' => now()]
                        ]
                    );
                }
            }
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $request->message,
            ]);

            DB::statement("INSERT INTO recipients (user_id, message_id) 
        select user_id, ? 
        from participants 
        where conversation_id = ?", [$message->id, $conversation->id]);

            $conversation->update(['last_message_id' => $message->id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $message;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $idw
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Recipient::where([
            'user_id' => Auth::user()->id,
            'message_id' => $id
        ])->delete();
        return [
            'message' => 'Message deleted successfully'
        ];
    }
}
