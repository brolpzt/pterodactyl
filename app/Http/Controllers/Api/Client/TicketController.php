<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\TicketDepartment;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Transformers\Api\Client\TicketTransformer;
use Pterodactyl\Transformers\Api\Client\TicketMessageTransformer;
use Pterodactyl\Transformers\Api\Client\TicketDepartmentTransformer;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Illuminate\Http\JsonResponse;

class TicketController extends ClientApiController
{
    /**
     * View all tickets for the user.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @return array
     */
    public function index(ClientApiRequest $request): array
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->with(['server', 'ticketDepartment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->fractal->collection($tickets)
            ->transformWith($this->getTransformer(TicketTransformer::class))
            ->toArray();
    }

    /**
     * Store a new ticket.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @return array
     */
    public function store(ClientApiRequest $request): array
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'department_id' => 'required|integer|exists:ticket_departments,id',
            'server_id' => 'nullable|exists:servers,id',
            'message' => 'required|string',
        ]);

        $department = TicketDepartment::findOrFail($request->input('department_id'));

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'server_id' => $request->input('server_id'),
            'department_id' => $department->id,
            'subject' => $request->input('subject'),
            'department' => $department->name, // Keeping string for backwards compatibility
            'status' => 'open',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
        ]);

        $ticket->load(['server', 'ticketDepartment']);

        return $this->fractal->item($ticket)
            ->transformWith($this->getTransformer(TicketTransformer::class))
            ->toArray();
    }

    /**
     * View all available ticket departments.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @return array
     */
    public function departments(ClientApiRequest $request): array
    {
        $departments = TicketDepartment::all();

        return $this->fractal->collection($departments)
            ->transformWith($this->getTransformer(TicketDepartmentTransformer::class))
            ->toArray();
    }

    /**
     * View a specific ticket with its messages.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @param int $ticketId
     * @return array
     */
    public function view(ClientApiRequest $request, int $ticketId): array
    {
        $ticket = Ticket::where('id', $ticketId)
            ->where('user_id', $request->user()->id)
            ->with(['server', 'ticketDepartment', 'messages.user'])
            ->firstOrFail();

        $ticketData = $this->fractal->item($ticket)
            ->transformWith($this->getTransformer(TicketTransformer::class))
            ->toArray();

        $messagesData = $this->fractal->collection($ticket->messages)
            ->transformWith($this->getTransformer(TicketMessageTransformer::class))
            ->toArray();

        $ticketData['messages'] = $messagesData['data'];

        return $ticketData;
    }

    /**
     * Add a message to a ticket.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @param int $ticketId
     * @return array
     */
    public function reply(ClientApiRequest $request, int $ticketId): array
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = Ticket::where('id', $ticketId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
        ]);

        $ticket->update(['status' => 'open']);
        $ticket->touch(); // Update the ticket's updated_at timestamp

        $message->load('user');

        return $this->fractal->item($message)
            ->transformWith($this->getTransformer(TicketMessageTransformer::class))
            ->toArray();
    }

    /**
     * Update the status of a ticket.
     *
     * @param \Pterodactyl\Http\Requests\Api\Client\ClientApiRequest $request
     * @param int $ticketId
     * @return array
     */
    public function status(ClientApiRequest $request, int $ticketId): array
    {
        $request->validate([
            'status' => 'required|string|in:open,closed',
        ]);

        $ticket = Ticket::where('id', $ticketId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ticket->update(['status' => $request->input('status')]);

        return $this->fractal->item($ticket)
            ->transformWith($this->getTransformer(TicketTransformer::class))
            ->toArray();
    }
}
