<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un numéro WhatsApp à côté de chaque numéro de téléphone.
 *
 * C'est le plus souvent le même, mais pas toujours : au Sénégal comme dans la
 * diaspora, beaucoup gardent un numéro local pour les appels et un autre, souvent
 * étranger, sur WhatsApp. Une colonne à part permet de dire lequel sert à quoi,
 * là où un seul champ obligeait à choisir.
 *
 * Même type et même nullabilité que les colonnes qu'elles accompagnent, et
 * placées juste après elles pour que les deux se lisent ensemble.
 *
 * Les lignes existantes reçoivent le téléphone comme valeur de départ : c'est le
 * cas général, et laisser la colonne vide se lirait à tort comme « pas de
 * WhatsApp » plutôt que comme « même numéro ».
 */
return new class extends Migration
{
    /** colonne WhatsApp => colonne téléphone qu'elle accompagne, par table. */
    private const CHAMPS = [
        'users' => [
            'whatsapp' => 'telephone',
        ],
        'investisseurs' => [
            'whatsapp' => 'telephone',
            'representant_legal_whatsapp' => 'representant_legal_telephone',
            'beneficiaire_whatsapp' => 'beneficiaire_telephone',
        ],
        'heritiers' => [
            'whatsapp' => 'telephone',
        ],
    ];

    public function up(): void
    {
        foreach (self::CHAMPS as $table => $champs) {
            Schema::table($table, function (Blueprint $t) use ($champs) {
                foreach ($champs as $whatsapp => $telephone) {
                    $t->string($whatsapp, 30)->nullable()->after($telephone);
                }
            });

            foreach ($champs as $whatsapp => $telephone) {
                DB::table($table)
                    ->whereNotNull($telephone)
                    ->where($telephone, '!=', '')
                    ->update([$whatsapp => DB::raw("`$telephone`")]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::CHAMPS as $table => $champs) {
            Schema::table($table, function (Blueprint $t) use ($champs) {
                $t->dropColumn(array_keys($champs));
            });
        }
    }
};
