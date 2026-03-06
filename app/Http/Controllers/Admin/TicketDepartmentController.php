<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\TicketDepartment;
use Prologue\Alerts\AlertsMessageBag;

class TicketDepartmentController extends Controller
{
    /**
     * @var \Prologue\Alerts\AlertsMessageBag
     */
    protected $alert;

    /**
     * TicketDepartmentController constructor.
     *
     * @param \Prologue\Alerts\AlertsMessageBag $alert
     */
    public function __construct(AlertsMessageBag $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Display the departments index.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $departments = TicketDepartment::withCount('tickets')->orderBy('name')->get();

        return view('admin.tickets.departments', [
            'departments' => $departments,
        ]);
    }

    /**
     * Create a new department.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:ticket_departments,name',
            'description' => 'nullable|string',
        ]);

        TicketDepartment::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        $this->alert->success('Successfully created new ticket department.')->flash();

        return redirect()->route('admin.departments');
    }

    /**
     * Delete a department.
     *
     * @param \Pterodactyl\Models\TicketDepartment $department
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(TicketDepartment $department): RedirectResponse
    {
        $department->delete();

        $this->alert->success('Successfully deleted the ticket department.')->flash();

        return redirect()->route('admin.departments');
    }
}
