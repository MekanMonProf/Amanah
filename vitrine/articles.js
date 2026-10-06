/*
 * Les actualités du Waqf.
 *
 * Pour publier une actualité, copiez un bloc { ... } ci-dessous, collez-le en
 * haut de la liste et changez son contenu. N'oubliez pas la virgule entre deux
 * blocs. Puis déposez ce fichier dans public_html, à la place de l'ancien.
 *
 *   id        un mot court, sans espace ni accent : il sert d'adresse à
 *             l'article (actualites.html?article=mon-id). Deux articles ne
 *             doivent pas avoir le même.
 *   date      au format année-mois-jour : '2026-10-04'.
 *   categorie un mot : Annonce, Vie du Waqf, Activités, Éducation…
 *   titre     le titre de l'article.
 *   resume    une ou deux phrases, affichées dans la liste.
 *   texte     le corps de l'article. Une ligne vide sépare deux paragraphes.
 *   image     facultatif : le chemin d'une image déposée dans images/.
 *   en, ar    facultatif : la traduction anglaise ou arabe, avec les mêmes
 *             champs titre, resume, texte. Sans traduction, l'article
 *             s'affiche en français.
 *
 * Les articles sont classés du plus récent au plus ancien, quel que soit
 * l'ordre dans ce fichier.
 */

var ARTICLES = [
  {
    id: 'site-trois-langues',
    date: '2026-10-04',
    categorie: 'Annonce',
    titre: 'Le site du Waqf est désormais en trois langues',
    resume: 'Français, anglais et arabe : choisissez votre langue en haut ou en bas de page.',
    texte: "Le site de Waqf Dolel Xamxam se lit désormais en français, en anglais et en arabe. Le choix de la langue se fait en haut de la page, ou en bas sur téléphone, et il est mémorisé pour vos prochaines visites.\n\nUn lien partagé ouvre la page dans la même langue : il suffit de copier l'adresse affichée.",
    en: {
      categorie: 'Announcement',
      titre: 'The Waqf website is now in three languages',
      resume: 'French, English and Arabic: pick your language at the top or bottom of the page.',
      texte: "The Waqf Dolel Xamxam website can now be read in French, English and Arabic. Choose your language at the top of the page, or at the bottom on a phone; your choice is remembered for your next visits.\n\nA shared link opens the page in the same language: just copy the address shown."
    },
    ar: {
      categorie: 'إعلان',
      titre: 'موقع الوقف متاح الآن بثلاث لغات',
      resume: 'الفرنسية والإنجليزية والعربية: اختر لغتك في أعلى الصفحة أو أسفلها.',
      texte: 'يمكن الآن قراءة موقع وقف Dolel Xamxam بالفرنسية والإنجليزية والعربية. يتم اختيار اللغة في أعلى الصفحة، أو في أسفلها على الهاتف، ويُحفظ اختيارك لزياراتك القادمة.\n\nالرابط المُشارَك يفتح الصفحة باللغة نفسها: يكفي نسخ العنوان الظاهر.'
    }
  },
  {
    id: 'etape-capital',
    date: '2026-10-03',
    categorie: 'Vie du Waqf',
    titre: 'Où en est le Waqf ? La création et la croissance du capital',
    resume: "Après la sensibilisation, le Waqf construit son capital. La construction d'écoles viendra ensuite.",
    texte: "Le Waqf avance en trois étapes. La première, la sensibilisation, a fait connaître le principe du waqf : un bien immobilisé pour toujours au service du bien commun.\n\nNous sommes aujourd'hui à la deuxième étape : la création et la croissance du capital. Chaque action Waqf ou commerciale renforce ce capital, investi par AND DOX dans des activités qui rapportent.\n\nLa troisième étape sera la construction d'écoles, financée par les revenus de ce capital.",
    en: {
      categorie: 'Life of the Waqf',
      titre: 'Where does the Waqf stand? Building and growing the capital',
      resume: 'After raising awareness, the Waqf is building its capital. Building schools will come next.',
      texte: "The Waqf moves forward in three stages. The first, raising awareness, made the principle of waqf known: an asset set aside forever for the common good.\n\nWe are now at the second stage: building and growing the capital. Every Waqf or commercial share strengthens this capital, which AND DOX invests in income-generating activities.\n\nThe third stage will be building schools, funded by the income from this capital."
    },
    ar: {
      categorie: 'حياة الوقف',
      titre: 'أين وصل الوقف؟ تكوين رأس المال وتنميته',
      resume: 'بعد مرحلة التوعية، يعمل الوقف على تكوين رأس ماله. ويأتي بعدها بناء المدارس.',
      texte: 'يتقدّم الوقف على ثلاث مراحل. الأولى، التوعية، عرّفت بمبدأ الوقف: مالٌ يُحبَس إلى الأبد في سبيل المنفعة العامة.\n\nنحن اليوم في المرحلة الثانية: تكوين رأس المال وتنميته. فكل سهم وقفي أو تجاري يقوّي رأس المال هذا، الذي تستثمره شركة AND DOX في أنشطة مُدِرّة للدخل.\n\nوستكون المرحلة الثالثة بناء المدارس، بتمويل من عائدات رأس المال هذا.'
    }
  },
  {
    id: 'comment-contribuer',
    date: '2026-10-03',
    categorie: 'Annonce',
    titre: 'Comment devenir actionnaire',
    resume: 'Actions Waqf et actions commerciales à 25 000 FCFA, payables par Wave, Orange Money ou Western Union.',
    texte: "Deux façons de contribuer, au même prix de 25 000 FCFA l'action.\n\nL'action Waqf est donnée au Waqf, définitivement : ses revenus financent la mission. L'action commerciale vous donne une part des bénéfices, sous forme de dividendes, qui dépendent des résultats et ne sont pas garantis.\n\nLe paiement se fait par Wave, Orange Money ou Western Union. Pour toute question, écrivez-nous sur WhatsApp au +221 78 127 44 76.",
    en: {
      categorie: 'Announcement',
      titre: 'How to become a shareholder',
      resume: 'Waqf shares and commercial shares at 25,000 FCFA, payable by Wave, Orange Money or Western Union.',
      texte: "Two ways to contribute, at the same price of 25,000 FCFA per share.\n\nA Waqf share is given to the Waqf, permanently: its income funds the mission. A commercial share gives you a part of the profits, as dividends, which depend on results and are not guaranteed.\n\nPayment is by Wave, Orange Money or Western Union. For any question, write to us on WhatsApp at +221 78 127 44 76."
    },
    ar: {
      categorie: 'إعلان',
      titre: 'كيف تصبح مساهمًا',
      resume: 'أسهم وقفية وأسهم تجارية بسعر ٢٥٬٠٠٠ فرنك إفريقي، تُدفع عبر Wave أو Orange Money أو Western Union.',
      texte: 'طريقتان للمساهمة، بالسعر نفسه: ٢٥٬٠٠٠ فرنك إفريقي للسهم.\n\nالسهم الوقفي يُوهَب للوقف نهائيًا، وتموّل عائداتُه الرسالة. أما السهم التجاري فيمنحك حصة من الأرباح في شكل توزيعات، تتوقف على النتائج وليست مضمونة.\n\nيتم الدفع عبر Wave أو Orange Money أو Western Union. لأي سؤال، راسلونا على واتساب، وتجدون الرقم في قسم «اتصل بنا» في الصفحة الرئيسية.'
    }
  }
];
