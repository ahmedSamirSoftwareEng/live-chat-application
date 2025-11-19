<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $conversations = $user->conversations()->paginate();
        return view('conversations.index', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        return $conversation->load('participants');
    }

    public function addParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        $conversation->participants()->attach($request->user_id, [
            'role' => 'member',
            'joined_at' => now()
        ]);
    }
    public function removeParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        $conversation->participants()->detach($request->user_id);
    }
}
