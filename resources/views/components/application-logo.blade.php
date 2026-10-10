{{--
    La version réduite, et non l'originale.

    Le fichier d'origine fait 1080 pixels de côté pour un affichage de quarante,
    et pèse 247 Ko ; celui-ci en fait 135 et pèse 11 Ko. L'original reste dans
    public/images/ : c'est lui qui sert à l'impression, où la définition compte.
--}}
<img src="{{ asset('images/logo_anddox-petit.png') }}" alt="AMANAH" {{ $attributes->class(['w-auto object-contain']) }}>
