# Debug Session: elementor-tag-warning [OPEN]

## Simptom
- Elementor afiseaza `Warning: Array to string conversion` in `elementor/core/dynamic-tags/manager.php` linia 66.
- Problema persista dupa corectiile initiale aplicate in JetSync.

## Ipoteze
1. Un tag JetSync activ inca intoarce `array` pentru `get_group()` sau alt camp pe care Elementor il trateaza ca `string`.
2. Un fallback anonim din `ElementorIntegration.php` este cel care provoaca warning-ul, nu clasele principale.
3. Serverul unde apare eroarea ruleaza cod vechi JetSync si nu patch-ul local deja aplicat.
4. OpCache sau alt cache mentine versiunea veche a claselor/tag-urilor.
5. Warning-ul este produs de alt plugin/tag Elementor, dar este vizibil exact in configuratia folosita de JetSync.

## Plan
- Adaug doar instrumentare de debug in integrarea Elementor JetSync.
- Colectez valori runtime pentru `name`, `class`, `group`, `title`, `categories` la inregistrarea tag-urilor.
- Compar evidenta cu ipotezele si apoi aplic fix minim.

## Evidenta
- Instrumentare adaugata in `src/Integration/Elementor/ElementorIntegration.php`.
- Debug server local pornit pentru sesiunea `elementor-tag-warning`.
- Se colecteaza la runtime: `class`, `name`, `title`, `group`, `categories`, `panel_template_setting`, `is_fallback`.
- Evidenta statica suplimentara: in Elementor `manager.php`, warning-ul de pe linia 66 apare in `preg_replace_callback()`, adica atunci cand parserul asteapta `string`, nu `array`.
- Fix minim aplicat: `PostUrlTag::get_value()` si `RelatedItemUrlTag::get_value()` intorc din nou `string`.
- Ipoteza confirmata partial: tag-urile URL care intorceau `array` pot produce exact warning-ul observat.
