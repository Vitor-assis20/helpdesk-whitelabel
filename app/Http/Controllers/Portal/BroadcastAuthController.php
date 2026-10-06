<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autoriza o canal privado do portal (portal.tickets.{id}).
 *
 * O /broadcasting/auth do Laravel exige usuário logado, e o cliente não tem
 * login: quem prova o acesso é o token do link do chamado, enviado no
 * cabeçalho X-Ticket-Token. A assinatura segue o protocolo do Pusher/Reverb.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request, BroadcastManager $broadcast): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string'],
        ]);

        abort_unless(preg_match('/^private-portal\.tickets\.(\d+)$/', $validated['channel_name'], $match), 403);

        // Busca sob o escopo da empresa do subdomínio: chamado de outra empresa não existe aqui.
        $ticket = Ticket::find((int) $match[1]);
        abort_unless($ticket && hash_equals($ticket->access_token, (string) $request->header('X-Ticket-Token')), 403);

        $auth = $broadcast->connection()->getPusher()->authorizeChannel($validated['channel_name'], $validated['socket_id']);

        return response()->json(json_decode($auth, true));
    }
}
