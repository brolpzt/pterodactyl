<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketDepartment;

class TicketController extends Controller
{
    /**
     * Display the tickets index.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $query = Ticket::with(['user', 'server'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->input('department'));
        }

        $tickets = $query->paginate(50);
        $departments = TicketDepartment::all();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'departments' => $departments,
            'filters' => [
                'status' => $request->input('status'),
                'department' => $request->input('department'),
            ],
        ]);
    }

    /**
     * View a specific ticket.
     *
     * @param \Pterodactyl\Models\Ticket $ticket
     * @return \Illuminate\View\View
     */
    public function view(Ticket $ticket): View
    {
        $ticket->load(['user', 'server', 'messages.user']);

        return view('admin.tickets.view', [
            'ticket' => $ticket,
        ]);
    }
}
