<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row for each listing that went live and was accepted by a saved
        // search. The row is the alert the user sees. The email about it
        // follows on the search's own timing.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            // Null until the alert has gone out in an email.
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            // A listing alerts a search once, ever. This is what makes
            // matching safe to repeat, and it also serves the lookup of one
            // search's alerts.
            $table->unique(['saved_search_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
