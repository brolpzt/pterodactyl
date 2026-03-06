<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Models\TicketDepartment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Prologue\Alerts\AlertsMessageBag;

class TicketController extends Controller
{
    /**
     * @var \Prologue\Alerts\AlertsMessageBag
     */
    protected $alert;

    /**
     * TicketController constructor.
     *
     * @param \Prologue\Alerts\AlertsMessageBag $alert
     */
    public function __construct(AlertsMessageBag $alert)
    {
        $this->alert = $alert;
    }
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

    /**
     * Reply to a ticket from admin.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Pterodactyl\Models\Ticket $ticket
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $hash = Str::random(40);
                Storage::putFileAs('tickets/' . $ticket->id, $file, $hash);

                TicketAttachment::create([
                    'ticket_message_id' => $message->id,
                    'filename' => $file->getClientOriginalName(),
                    'hash' => $hash,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $ticket->update(['status' => 'open']);
        $ticket->touch();

        $this->alert->success('Record created successfully.')->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    /**
     * Toggle ticket status.
     *
     * @param \Pterodactyl\Models\Ticket $ticket
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleStatus(Ticket $ticket): RedirectResponse
    {
        $status = $ticket->status === 'open' ? 'closed' : 'open';
        $ticket->update(['status' => $status]);

        $this->alert->success('Ticket status updated successfully.')->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    /**
     * Download a ticket attachment for admin.
     *
     * @param string $hash
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function attachment(string $hash): StreamedResponse
    {
        $attachment = TicketAttachment::where('hash', $hash)->firstOrFail();
        $message = $attachment->message;

        return Storage::download(
            'tickets/' . $message->ticket_id . '/' . $attachment->hash,
            $attachment->filename
        );
    }
}
