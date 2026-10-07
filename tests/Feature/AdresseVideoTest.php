<?php

namespace Tests\Feature;

use App\Support\AdresseVideo;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Les formes d'adresse YouTube ramenées à celle qui s'intègre. */
class AdresseVideoTest extends TestCase
{
    public static function formes(): array
    {
        $attendu = 'https://www.youtube.com/embed/aqz-KE-bpKQ';

        return [
            'barre d\'adresse' => ['https://www.youtube.com/watch?v=aqz-KE-bpKQ', $attendu],
            'bouton Partager' => ['https://youtu.be/aqz-KE-bpKQ', $attendu],
            'menu Intégrer' => ['https://www.youtube.com/embed/aqz-KE-bpKQ', $attendu],
            'sans protocole www' => ['https://youtube.com/watch?v=aqz-KE-bpKQ', $attendu],
            'version mobile' => ['https://m.youtube.com/watch?v=aqz-KE-bpKQ', $attendu],
            'short' => ['https://www.youtube.com/shorts/aqz-KE-bpKQ', $attendu],
            'direct' => ['https://www.youtube.com/live/aqz-KE-bpKQ', $attendu],
            'avec une playlist' => ['https://www.youtube.com/watch?v=aqz-KE-bpKQ&list=PL123', $attendu],
            'espaces autour' => ['  https://youtu.be/aqz-KE-bpKQ  ', $attendu],
        ];
    }

    #[DataProvider('formes')]
    public function test_les_formes_de_youtube_sont_ramenees_a_l_integration(string $donnee, string $attendu): void
    {
        $this->assertSame($attendu, AdresseVideo::normaliser($donnee));
    }

    public function test_le_sans_pistage_est_conserve(): void
    {
        // Le choix appartient à qui a collé l'adresse : on ne le défait pas.
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ',
            AdresseVideo::normaliser('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ'),
        );
    }

    public function test_l_instant_de_depart_suit_le_changement_de_forme(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/aqz-KE-bpKQ?start=90',
            AdresseVideo::normaliser('https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=90s'),
        );

        $this->assertSame(
            'https://www.youtube.com/embed/aqz-KE-bpKQ?start=90',
            AdresseVideo::normaliser('https://youtu.be/aqz-KE-bpKQ?t=90'),
        );

        $this->assertSame(
            'https://www.youtube.com/embed/aqz-KE-bpKQ?start=3725',
            AdresseVideo::normaliser('https://www.youtube.com/watch?v=aqz-KE-bpKQ&t=1h2m5s'),
        );
    }

    public function test_une_adresse_d_un_autre_hebergeur_ressort_intacte(): void
    {
        // L'application ne prétend pas connaître tous les hébergeurs : un lien
        // Vimeo ou celui d'un serveur interne doit passer tel quel.
        foreach ([
            'https://player.vimeo.com/video/123456',
            'https://videos.example.test/aide/demarrer.mp4',
        ] as $adresse) {
            $this->assertSame($adresse, AdresseVideo::normaliser($adresse));
        }
    }

    public function test_une_adresse_youtube_sans_identifiant_ressort_intacte(): void
    {
        // Rien à convertir : on ne fabrique pas un lien d'intégration vide,
        // la validation « url » du formulaire reste seule juge.
        $this->assertSame(
            'https://www.youtube.com/feed/subscriptions',
            AdresseVideo::normaliser('https://www.youtube.com/feed/subscriptions'),
        );
    }

    public function test_le_vide_rend_null(): void
    {
        $this->assertNull(AdresseVideo::normaliser(''));
        $this->assertNull(AdresseVideo::normaliser('   '));
        $this->assertNull(AdresseVideo::normaliser(null));
    }
}
