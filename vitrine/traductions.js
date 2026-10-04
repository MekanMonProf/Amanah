/*
 * Vitrine en trois langues.
 *
 * Le français est écrit dans la page elle-même : c'est ce qu'on voit sans
 * JavaScript, et ce que lisent les moteurs de recherche. Il est relevé au
 * chargement pour pouvoir y revenir. Ce fichier ne porte que l'anglais et
 * l'arabe ; une clé absente retombe sur le français.
 *
 * Langue choisie : ?lang=fr|en|ar dans l'adresse, sinon le dernier choix du
 * visiteur, sinon le français.
 */

var TRADUCTIONS = {
  en: {
    'meta.titre': 'Waqf Dolel Xamxam — Strengthening knowledge',
    'meta.description': "Waqf Dolel Xamxam funds daaras and free education, from the early grades to the doctorate, through the activities of its subsidiary AND DOX. Waqf shares, commercial shares, gifts in memory of a loved one.",
    'langue.groupe': 'Language',

    'nav.mission': 'Our mission',
    'nav.activites': 'Our activities',
    'nav.actions': 'Our shares',
    'nav.contact': 'Contact',
    'btn.amanah': 'AMANAH space',
    'btn.actionnaire': 'Shareholder space',

    'hero.titre': 'Strengthening knowledge,<em>for today and for ever.</em>',
    'hero.texte': 'A waqf is an asset set aside forever for the common good. The capital entrusted is never spent: it is invested in income-generating activities, and that income funds daaras and free education, from the early grades all the way to the doctorate.',
    'hero.cta': 'Become a shareholder',
    'hero.espace': 'Go to my space →',
    'hero.logo': 'Waqf Dolel Xamxam logo: a tree growing from an open book, under an arch',
    'hero.gere': 'Activities managed by',

    'mission.titre': 'Our name, our mission',
    'mission.dolel': 'to strengthen, to support',
    'mission.xamxam': 'knowledge',
    'mission.texte': 'Waqf Dolel Xamxam brings together the contributions of everyone who wants knowledge to be passed on, in Senegal and beyond. The capital entrusted is never spent: it is invested by AND DOX, a subsidiary of the Waqf, in income-generating activities. That income funds daaras and free education at every stage of learning.',
    'parcours.daaras': 'Daaras',
    'parcours.daaras.texte': "Learning the Qur'an and the religious sciences",
    'parcours.petites': 'Early grades',
    'parcours.petites.texte': 'Preschool and primary school',
    'parcours.secondaire': 'Middle and high school',
    'parcours.secondaire.texte': 'Lower and upper secondary',
    'parcours.universite': 'University',
    'parcours.universite.texte': "From the bachelor's degree to the doctorate",
    'mission.gratuit': 'Free education, so that knowledge never depends on what a family can afford.',

    'route.titre': 'Our roadmap',
    'route.chapeau': "From today's generosity to tomorrow's education, in three stages.",
    'route.etape1': 'Stage 1',
    'route.etape2': 'Stage 2',
    'route.etape3': 'Stage 3',
    'route.e1.titre': 'Raising awareness of waqf',
    'route.e1.texte': 'Encouraging everyone to contribute to the waqf: a lasting act of generosity that benefits the community over the long term.',
    'route.ici': 'We are here',
    'route.e2.titre': 'Building and growing the capital',
    'route.e2.texte': "Building capital from the waqf's funds and creating the company AND DOX, in which investors take a share of the capital.",
    'route.e2.waqf': 'The Waqf, largest shareholder',
    'route.e2.autres': 'Shareholders and investors',
    'route.e2.objectif': 'Goal: grow this capital to several billion CFA francs.',
    'route.e3.titre': 'Building schools',
    'route.e3.texte': 'Devoting part of the profits the Waqf receives from AND DOX to building and developing schools, and to providing quality education.',
    'route.flux': 'Waqf, then investment, then education',
    'flux.waqf': 'Waqf',
    'flux.waqf.texte': 'Lasting impact',
    'flux.invest': 'Investment',
    'flux.invest.texte': 'Capital growth',
    'flux.education': 'Education',
    'flux.education.texte': 'A better future',

    'activites.titre': 'Capital at work',
    'activites.gere': 'AND DOX, a subsidiary of the Waqf, manages the activities in which the capital is invested: concrete projects that generate income here in Senegal.',
    'activites.aujourdhui': 'Today',
    'activites.enactivite': 'Operating',
    'activites.demain': 'Tomorrow',
    'activites.endeveloppement': 'In development',
    'activites.demain.texte': 'Other promising sectors, chosen for their lasting income and their compliance with the ethics of waqf, will broaden the funding of daaras and free education.',
    'act.transport': 'Transport',
    'act.transport.texte': 'Vehicles put into service to carry people and goods.',
    'act.motos': 'Motorcycle sales',
    'act.motos.texte': 'Selling motorcycles, a tool for work and mobility for many people.',
    'act.agro': 'Agribusiness',
    'act.agro.texte': 'Farming projects that produce, feed people and generate income.',
    'act.immobilier': 'Rental property',
    'act.immobilier.texte': 'Homes, offices and commercial premises that bring in regular rent.',
    'act.elevage': 'Livestock',
    'act.elevage.texte': 'Poultry farming, and fattening cattle and sheep, especially for Tabaski.',
    'act.transformation': 'Food processing',
    'act.transformation.texte': 'Local cereals, juices, oils, and packaging the produce of our fields.',
    'act.solaire': 'Solar energy',
    'act.solaire.texte': 'Installing and selling solar kits for homes, shops and daaras.',
    'act.commerce': 'Trade and distribution',
    'act.commerce.texte': 'Supplying basic necessities at fair prices.',
    'act.edition': 'Publishing and school supplies',
    'act.edition.texte': "Textbooks, copies of the Qur'an and supplies, serving pupils as well as the Waqf's income.",

    'hadith.texte': '“When a person dies, their deeds come to an end except for three: an ongoing charity, knowledge that benefits others, or a righteous child who prays for them.”',
    'hadith.source': 'Reported by Muslim',
    'hadith.lien': 'Waqf Dolel Xamxam brings together two of these three deeds: a charity that lasts and knowledge that benefits.',

    'fonct.titre': 'How the Waqf works',
    'fonct.preserve': 'The capital is preserved',
    'fonct.preserve.texte': 'A Waqf share cannot be transferred, cancelled or withdrawn. It is given forever, and its capital is invested, never spent.',
    'fonct.revenus': 'The income serves the cause',
    'fonct.revenus.texte': 'For Waqf shares, what the activities earn does not go back to the donor: it funds the mission.',
    'fonct.trace': 'Everything is recorded',
    'fonct.trace.texte': 'Every contributor receives a certificate and can follow their account online in their personal space.',

    'contrib.titre': 'Become a shareholder',
    'contrib.chapeau': 'Both types of share fund the same activities, at the same price. What differs is what you expect from them. You can take one or several, all at once or over time.',
    'contrib.waqf': 'Waqf share',
    'contrib.waqf.titre': 'Give forever',
    'contrib.prix': 'CFA francs per share',
    'contrib.waqf.1': 'The share is given to the Waqf, permanently.',
    'contrib.waqf.2': 'The income it produces funds the mission, with nothing returned to the donor.',
    'contrib.waqf.3': 'It is an ongoing charity that keeps working after you.',
    'contrib.comm': 'Commercial share',
    'contrib.comm.titre': 'Invest and receive dividends',
    'contrib.comm.1': 'You receive your share of the profits as dividends.',
    'contrib.comm.2': 'Your shares can be transferred or cancelled, under the conditions in force.',
    'contrib.comm.3': 'You follow your dividends and your balance in your space.',
    'contrib.avertissement': 'Dividends depend on the results of the activities and are not guaranteed.',
    'contrib.offrande': 'Give in memory of a loved one',
    'contrib.offrande.texte': 'You can dedicate Waqf shares to a loved one who has passed away. Their name appears on the gift certificate, and the endowment keeps working in their name.',
    'contrib.paiement': '<strong>Payment methods:</strong> Wave, Orange Money, Western Union.',

    'espace.titre': 'Your online space',
    'espace.texte': 'Already a shareholder or contributor? Your space shows your shares, your account history, your statements and your certificates, available at any time.',
    'espace.amanah': 'AMANAH space →',
    'espace.actionnaire': 'Shareholder space →',

    'contact.titre': 'Contact',
    'contact.tel': 'Phone / WhatsApp',
    'contact.whatsapp': 'Message us on WhatsApp',
    'contact.email': 'Email',
    'contact.adresse': 'Address',
    'contact.ville': 'Dakar, Senegal',

    'pied.filiale': 'AND DOX, a subsidiary of Waqf Dolel Xamxam',
    'onglet.accueil': 'Home',
    'onglet.activites': 'Activities',
    'onglet.actions': 'Shares',
    'onglet.contact': 'Contact',
    'onglet.espace': 'My space'
  },

  ar: {
    'meta.titre': 'وقف Dolel Xamxam — تعزيز المعرفة',
    'meta.description': 'يموّل وقف Dolel Xamxam الدارات القرآنية والتعليم المجاني، من الصفوف الأولى حتى الدكتوراه، بفضل أنشطة شركته التابعة AND DOX. أسهم وقفية، وأسهم تجارية، ووقف على روح متوفى.',
    'langue.groupe': 'اللغة',

    'nav.mission': 'رسالتنا',
    'nav.activites': 'أنشطتنا',
    'nav.actions': 'أسهمنا',
    'nav.contact': 'اتصل بنا',
    'btn.amanah': 'فضاء AMANAH',
    'btn.actionnaire': 'فضاء المساهم',

    'hero.titre': 'تعزيز المعرفة،<em>اليوم وإلى الأبد.</em>',
    'hero.texte': 'الوقف مالٌ يُحبَس إلى الأبد في سبيل المنفعة العامة. ورأس المال الموقوف لا يُنفَق أبدًا، بل يُستثمر في أنشطة مُدِرّة للدخل، وتموّل عائداتُه الداراتِ القرآنيةَ والتعليمَ المجانيَّ، من الصفوف الأولى حتى الدكتوراه.',
    'hero.cta': 'كن مساهمًا',
    'hero.espace': 'الدخول إلى فضائي ←',
    'hero.logo': 'شعار وقف Dolel Xamxam: شجرة تنبت من كتاب مفتوح تحت قوس',
    'hero.gere': 'أنشطة تديرها',

    'mission.titre': 'اسمنا ورسالتنا',
    'mission.dolel': 'التعزيز والدعم',
    'mission.xamxam': 'المعرفة',
    'mission.texte': 'يجمع وقف Dolel Xamxam مساهمات كل من يريد أن تنتقل المعرفة في السنغال وخارجها. ورأس المال الموقوف لا يُنفَق أبدًا، بل تستثمره شركة AND DOX، التابعة للوقف، في أنشطة مُدِرّة للدخل. وتموّل هذه العائداتُ الداراتِ القرآنيةَ والتعليمَ المجانيَّ في كل مراحل الدراسة.',
    'parcours.daaras': 'الدارات القرآنية',
    'parcours.daaras.texte': 'تعليم القرآن الكريم والعلوم الشرعية',
    'parcours.petites': 'الصفوف الأولى',
    'parcours.petites.texte': 'التعليم ما قبل المدرسي والابتدائي',
    'parcours.secondaire': 'الإعدادي والثانوي',
    'parcours.secondaire.texte': 'المرحلتان المتوسطة والثانوية',
    'parcours.universite': 'الجامعة',
    'parcours.universite.texte': 'من الإجازة حتى الدكتوراه',
    'mission.gratuit': 'تعليم مجاني، حتى لا تتوقف المعرفة على إمكانات الأسرة.',

    'route.titre': 'خارطة طريقنا',
    'route.chapeau': 'من كرم اليوم إلى تعليم الغد، في ثلاث مراحل.',
    'route.etape1': 'المرحلة الأولى',
    'route.etape2': 'المرحلة الثانية',
    'route.etape3': 'المرحلة الثالثة',
    'route.e1.titre': 'التوعية بالوقف',
    'route.e1.texte': 'تشجيع الجميع على المساهمة في الوقف: عملُ خيرٍ دائم ينفع المجتمع على المدى الطويل.',
    'route.ici': 'نحن هنا',
    'route.e2.titre': 'تكوين رأس المال وتنميته',
    'route.e2.texte': 'تكوين رأس مال من أموال الوقف، وإنشاء شركة AND DOX التي يشارك المستثمرون في رأس مالها.',
    'route.e2.waqf': 'الوقف، أكبر مساهم',
    'route.e2.autres': 'المساهمون والمستثمرون',
    'route.e2.objectif': 'الهدف: تنمية رأس المال هذا حتى يبلغ عدة مليارات من الفرنكات الإفريقية.',
    'route.e3.titre': 'بناء المدارس',
    'route.e3.texte': 'تخصيص جزء من الأرباح التي يتلقاها الوقف من AND DOX لبناء المدارس وتطويرها، وتقديم تعليم جيد.',
    'route.flux': 'الوقف، ثم الاستثمار، ثم التعليم',
    'flux.waqf': 'الوقف',
    'flux.waqf.texte': 'أثر دائم',
    'flux.invest': 'الاستثمار',
    'flux.invest.texte': 'نمو رأس المال',
    'flux.education': 'التعليم',
    'flux.education.texte': 'مستقبل أفضل',

    'activites.titre': 'رأس مال يعمل',
    'activites.gere': 'تدير شركة AND DOX، التابعة للوقف، الأنشطة التي يُستثمر فيها رأس المال: مشاريع ملموسة تُدرّ الدخل هنا في السنغال.',
    'activites.aujourdhui': 'اليوم',
    'activites.enactivite': 'قيد التشغيل',
    'activites.demain': 'غدًا',
    'activites.endeveloppement': 'قيد التطوير',
    'activites.demain.texte': 'وستأتي قطاعات واعدة أخرى، مختارة لعائداتها الدائمة وتوافقها مع أخلاقيات الوقف، لتوسيع تمويل الدارات القرآنية والتعليم المجاني.',
    'act.transport': 'النقل',
    'act.transport.texte': 'مركبات مخصصة لنقل الأشخاص والبضائع.',
    'act.motos': 'بيع الدراجات النارية',
    'act.motos.texte': 'بيع الدراجات النارية، وهي وسيلة عمل وتنقل لكثير من الناس.',
    'act.agro': 'الأعمال الزراعية',
    'act.agro.texte': 'مشاريع زراعية تُنتج وتُطعم وتُدرّ الدخل.',
    'act.immobilier': 'العقارات المؤجَّرة',
    'act.immobilier.texte': 'مساكن ومكاتب ومحلات تجارية تُدرّ إيجارات منتظمة.',
    'act.elevage': 'تربية المواشي',
    'act.elevage.texte': 'تربية الدواجن وتسمين الأبقار والأغنام، لا سيما لعيد الأضحى (التباسكي).',
    'act.transformation': 'التصنيع الغذائي',
    'act.transformation.texte': 'الحبوب المحلية والعصائر والزيوت، وتعبئة منتجات حقولنا.',
    'act.solaire': 'الطاقة الشمسية',
    'act.solaire.texte': 'تركيب وبيع أطقم الطاقة الشمسية للبيوت والمتاجر والدارات القرآنية.',
    'act.commerce': 'التجارة والتوزيع',
    'act.commerce.texte': 'توفير المواد الأساسية بأسعار عادلة.',
    'act.edition': 'النشر واللوازم المدرسية',
    'act.edition.texte': 'كتب مدرسية ومصاحف ولوازم، تخدم التلاميذ وتعود بالدخل على الوقف.',

    'hadith.texte': '«إِذَا مَاتَ الإِنْسَانُ انْقَطَعَ عَنْهُ عَمَلُهُ إِلَّا مِنْ ثَلَاثَةٍ: إِلَّا مِنْ صَدَقَةٍ جَارِيَةٍ، أَوْ عِلْمٍ يُنْتَفَعُ بِهِ، أَوْ وَلَدٍ صَالِحٍ يَدْعُو لَهُ»',
    'hadith.source': 'رواه مسلم',
    'hadith.lien': 'ويجمع وقف Dolel Xamxam بين اثنين من هذه الأعمال الثلاثة: صدقة جارية وعلم يُنتفع به.',

    'fonct.titre': 'كيف يعمل الوقف',
    'fonct.preserve': 'رأس المال محفوظ',
    'fonct.preserve.texte': 'لا يجوز التنازل عن سهم الوقف ولا شطبه ولا سحبه. فهو موقوف إلى الأبد، ورأس ماله يُستثمر ولا يُنفَق.',
    'fonct.revenus': 'العائدات في خدمة الرسالة',
    'fonct.revenus.texte': 'بالنسبة إلى أسهم الوقف، لا تعود عائدات الأنشطة إلى الواقف، بل تموّل الرسالة.',
    'fonct.trace': 'كل شيء موثَّق',
    'fonct.trace.texte': 'يتلقى كل مساهم شهادة، ويمكنه متابعة حسابه عبر الإنترنت في فضائه الشخصي.',

    'contrib.titre': 'كن مساهمًا',
    'contrib.chapeau': 'يموّل نوعا الأسهم الأنشطةَ نفسها وبالسعر نفسه، والفرق فيما تنتظره منها. يمكنك أن تأخذ سهمًا واحدًا أو عدة أسهم، دفعة واحدة أو على مراحل.',
    'contrib.waqf': 'سهم الوقف',
    'contrib.waqf.titre': 'عطاء إلى الأبد',
    'contrib.prix': 'فرنك إفريقي للسهم',
    'contrib.waqf.1': 'يُوقَف السهم لصالح الوقف بشكل نهائي.',
    'contrib.waqf.2': 'تموّل العائداتُ التي يُدرّها الرسالةَ، دون أن يعود شيء منها إلى الواقف.',
    'contrib.waqf.3': 'إنه صدقة جارية يستمر أجرها بعدك.',
    'contrib.comm': 'السهم التجاري',
    'contrib.comm.titre': 'استثمر واحصل على الأرباح',
    'contrib.comm.1': 'تحصل على نصيبك من الأرباح في صورة أرباح موزَّعة.',
    'contrib.comm.2': 'يمكن التنازل عن أسهمك أو شطبها وفق الشروط المعمول بها.',
    'contrib.comm.3': 'تتابع أرباحك ورصيدك في فضائك.',
    'contrib.avertissement': 'تتوقف الأرباح على نتائج الأنشطة، وهي غير مضمونة.',
    'contrib.offrande': 'وقفٌ على روح متوفى',
    'contrib.offrande.texte': 'يمكنك أن تهدي أسهم وقف إلى قريب متوفى. يُذكر اسمه في شهادة الإهداء، ويستمر الوقف في العمل باسمه.',
    'contrib.paiement': '<strong>وسائل الدفع:</strong> Wave وOrange Money وWestern Union.',

    'espace.titre': 'فضاؤك على الإنترنت',
    'espace.texte': 'هل أنت مساهم بالفعل؟ يعرض لك فضاؤك أسهمك وسجل حسابك وكشوفاتك وشهاداتك، متاحة في أي وقت.',
    'espace.amanah': 'فضاء AMANAH ←',
    'espace.actionnaire': 'فضاء المساهم ←',

    'contact.titre': 'اتصل بنا',
    'contact.tel': 'الهاتف / واتساب',
    'contact.whatsapp': 'راسلنا عبر واتساب',
    'contact.email': 'البريد الإلكتروني',
    'contact.adresse': 'العنوان',
    'contact.ville': 'داكار، السنغال',

    'pied.filiale': 'AND DOX، شركة تابعة لوقف Dolel Xamxam',
    'onglet.accueil': 'الرئيسية',
    'onglet.activites': 'الأنشطة',
    'onglet.actions': 'الأسهم',
    'onglet.contact': 'اتصل',
    'onglet.espace': 'فضائي'
  }
};

(function () {
  var LANGUES = ['fr', 'en', 'ar'];
  var racine = document.documentElement;
  var meta = document.querySelector('meta[name="description"]');

  // Le français, relevé dans la page telle qu'elle est écrite.
  var francais = { 'meta.titre': document.title, 'meta.description': meta ? meta.content : '' };
  document.querySelectorAll('[data-i18n]').forEach(function (el) { francais[el.dataset.i18n] = el.innerHTML; });
  document.querySelectorAll('[data-i18n-alt]').forEach(function (el) { francais[el.dataset.i18nAlt] = el.alt; });
  document.querySelectorAll('[data-i18n-aria-label]').forEach(function (el) { francais[el.dataset.i18nAriaLabel] = el.getAttribute('aria-label'); });

  function texte(langue, cle) {
    var t = TRADUCTIONS[langue];
    return t && t[cle] != null ? t[cle] : francais[cle];
  }

  // Les nombres s'écrivent dans les chiffres et la ponctuation de la langue :
  // 25 000 en français, 25,000 en anglais, ٢٥٬٠٠٠ en arabe.
  var LOCALES = { fr: 'fr-FR', en: 'en-GB', ar: 'ar-u-nu-arab' };
  var annee = document.getElementById('annee');
  if (annee) { annee.dataset.nombre = String(new Date().getFullYear()); }

  function chiffres(langue, texte) {
    if (langue !== 'ar') { return texte; }
    return texte.replace(/[0-9]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'.charAt(+c); });
  }

  // Un numéro se lit de gauche à droite, même en arabe. Laissé en texte, ses
  // groupes de chiffres arabes s'inversent (« +٧٦ ٤٤ ١٢٧ ٧٨ ٢٢١ ») : l'espace
  // entre deux groupes prend le sens de droite à gauche. Chaque groupe devient
  // donc une boîte, rangée de gauche à droite par la mise en page (.groupes).
  function groupes(langue, texte) {
    return texte.split(/\s+/).map(function (g) {
      var span = document.createElement('span');
      span.textContent = chiffres(langue, g);
      return span;
    });
  }

  function nombres(langue) {
    document.querySelectorAll('[data-nombre]').forEach(function (el) {
      var options = el.hasAttribute('data-sans-separateur') ? { useGrouping: false } : {};
      el.textContent = new Intl.NumberFormat(LOCALES[langue], options).format(Number(el.dataset.nombre));
    });
    // Un numéro de téléphone garde ses espaces : seuls les chiffres changent.
    document.querySelectorAll('[data-chiffres]').forEach(function (el) {
      if (el.dataset.chiffresOrigine == null) { el.dataset.chiffresOrigine = el.textContent.trim(); }
      el.replaceChildren.apply(el, groupes(langue, el.dataset.chiffresOrigine));
      el.classList.add('groupes');
    });
  }

  function appliquer(langue) {
    if (LANGUES.indexOf(langue) < 0) { langue = 'fr'; }
    nombres(langue);

    document.querySelectorAll('[data-i18n]').forEach(function (el) { el.innerHTML = texte(langue, el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-alt]').forEach(function (el) { el.alt = texte(langue, el.dataset.i18nAlt); });
    document.querySelectorAll('[data-i18n-aria-label]').forEach(function (el) { el.setAttribute('aria-label', texte(langue, el.dataset.i18nAriaLabel)); });
    document.title = texte(langue, 'meta.titre');
    if (meta) { meta.content = texte(langue, 'meta.description'); }

    racine.lang = langue;
    racine.dir = langue === 'ar' ? 'rtl' : 'ltr';
    document.querySelectorAll('.langues button').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.lang === langue ? 'true' : 'false');
    });

    try { localStorage.setItem('vitrine-langue', langue); } catch (e) {}
  }

  var demandee = new URLSearchParams(location.search).get('lang');
  var memorisee = null;
  try { memorisee = localStorage.getItem('vitrine-langue'); } catch (e) {}
  appliquer(demandee || memorisee || 'fr');

  document.querySelectorAll('.langues button').forEach(function (b) {
    b.addEventListener('click', function () {
      appliquer(b.dataset.lang);
      // L'adresse suit la langue, pour qu'un lien partagé ouvre la même version.
      var url = new URL(location.href);
      url.searchParams.set('lang', b.dataset.lang);
      history.replaceState(null, '', url);
    });
  });
})();
