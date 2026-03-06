<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketDepartmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
        
        // Add a foreign key to the tickets table for the department
        Schema::table('tickets', function (Blueprint $table) {
            // we remove the old string "department" column in down/up if they already existed
            // and instead make a relationship. However to avoid complex migrations for existing tickets,
            // we'll add department_id. We're keeping the standard minimal schema implementation style.
            $table->unsignedBigInteger('department_id')->nullable()->after('server_id');
            $table->foreign('department_id')->references('id')->on('ticket_departments')->onDelete('set null');
            
            // Note: In a real production migration with existing data you would want to migrate the string data. 
            // For this new feature we will just add the column.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
        
        Schema::dropIfExists('ticket_departments');
    }
}
