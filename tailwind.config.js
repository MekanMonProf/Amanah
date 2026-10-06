import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Les couleurs de la maison, posées ici et nulle part ailleurs.
 *
 * Elles sortent du logo AND DOX, dont le dégradé va d'un vert vif (#56A724, le
 * grand A) à un vert forêt (#054A14, le creux). La gamme `primaire` relie les
 * deux : le vif au milieu, le sombre en bas, et les teintes claires obtenues en
 * le mêlant au blanc. Ce glissement de teinte le long de l'échelle est celui du
 * logo lui-même — c'est ce qui fait que l'interface lui ressemble.
 *
 * Chaque valeur a été vérifiée au contraste. Les repères utiles :
 *   primaire-700  texte blanc dessus, 6,4:1   → boutons, liens, états actifs
 *   primaire-600  texte blanc dessus, 4,5:1   → pastille active sur fond sombre
 *   nuit-900      texte blanc dessus, 17:1    → barre latérale, bandeaux
 *   or-700        sur fond blanc, 5,8:1       → ce qui demande une décision
 *
 * L'or n'est pas décoratif : il est réservé à l'argent qui attend un geste —
 * un capital radié non versé, un dossier à compléter. S'il sert partout, il ne
 * signale plus rien.
 */
const primaire = {
    50: '#F5FAF2',
    100: '#E7F3E0',
    200: '#CCE5BD',
    300: '#ABD392',
    400: '#80BD5B',
    500: '#56A724',
    600: '#3A861E',
    700: '#246D1A',
    800: '#125917',
    900: '#054A14',
    950: '#03290B',
};

const nuit = {
    700: '#0A4015',
    800: '#063210',
    900: '#03290B',
    950: '#021A07',
};

const or = {
    50: '#FDF8EC',
    100: '#F8ECCD',
    300: '#E3C47E',
    500: '#C9961F',
    600: '#A87A16',
    700: '#845E12',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primaire,
                nuit,
                or,
            },
            borderRadius: {
                // Les cartes respirent plus que les champs : deux rayons, pas un.
                carte: '1rem',
                champ: '0.625rem',
            },
        },
    },

    plugins: [forms],
};
