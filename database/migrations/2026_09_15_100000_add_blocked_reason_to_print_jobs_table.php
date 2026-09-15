<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            // Pourquoi une tâche encore « en attente » n'a pas été remise à
            // CUPS. Renseignée uniquement tant que l'imprimante est bloquée,
            // elle est remise à null dès que la tâche part.
            //
            // Le statut ne bouge pas pour autant : la tâche est bel et bien en
            // attente, au sens où elle l'a toujours été. Seule s'y ajoute la
            // raison de ne pas avancer, que l'interface montre au membre — une
            // colonne suffit donc là où un cinquième statut aurait demandé de
            // reconstruire la contrainte CHECK de `status` sous SQLite.
            $table->text('blocked_reason')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn('blocked_reason');
        });
    }
};
