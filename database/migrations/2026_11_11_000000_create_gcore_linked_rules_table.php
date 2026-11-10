<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Extra ACL rules an egg port slot can opt into (e.g. a booster allowlist). The port
        // sync adds/removes the server ports in their dport_list.
        Schema::create('gcore_linked_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('policy', 64);
            $table->string('proto', 16)->nullable();
            $table->json('sip_list')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcore_linked_rules');
    }
};
