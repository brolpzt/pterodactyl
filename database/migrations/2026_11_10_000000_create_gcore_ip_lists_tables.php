<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remote IPv4 lists (Cloudflare, Valve AS32590, Google, …) downloaded on a schedule.
        Schema::create('gcore_ip_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('url', 512);
            $table->unsignedSmallInteger('refresh_hours')->default(24);
            $table->boolean('enabled')->default(true);
            // Current accepted CIDRs (IPv4 only, aggregated). Never replaced by an empty/broken download.
            $table->json('entries')->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->char('entries_hash', 64)->nullable();
            $table->string('last_status', 16)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gcore_ip_list_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gcore_ip_list_id')->constrained('gcore_ip_lists')->cascadeOnDelete();
            // changed | unchanged | rejected | error
            $table->string('status', 16);
            // schedule | manual | force
            $table->string('trigger', 16)->default('schedule');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('previous_count')->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->json('added')->nullable();
            $table->json('removed')->nullable();
            $table->unsignedInteger('ignored_ipv6')->default(0);
            $table->unsignedInteger('ignored_invalid')->default(0);
            // Per-profile result of pushing the new entries to Gcore.
            $table->json('profiles')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Which ACL rule of which Gcore profile pulls its sip_list from which lists.
        // Gcore rules have no id/comment, so a rule is found again by policy+proto+dport+sport
        // (rules with sip are frozen for the port sync, so those never change by themselves).
        Schema::create('gcore_ip_list_bindings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('profile_ip', 45)->nullable();
            $table->string('policy', 64);
            $table->json('proto_list');
            $table->json('dport_list');
            $table->json('sport_list');
            $table->json('list_ids');
            // IPs typed by hand in the editor (kept on every refresh).
            $table->json('manual_sips');
            // List-derived CIDRs last written to Gcore (used to find and replace them).
            $table->json('applied_sips');
            // ok | pending | error | orphan
            $table->string('status', 16)->default('ok');
            $table->text('last_error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcore_ip_list_bindings');
        Schema::dropIfExists('gcore_ip_list_logs');
        Schema::dropIfExists('gcore_ip_lists');
    }
};
