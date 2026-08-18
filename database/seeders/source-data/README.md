# Données sources

Extraction de `LISTES_DES_MALADIES.docx` (fourni par l'utilisateur,
2026-08-19), parsée programmatiquement (jamais retranscrite à la main —
voir l'erreur évitée sur les codes pays, documentée dans `CLAUDE.md`).

- `countries.json` : 46 pays, `{"code": "nom"}`.
- `diseases.json` : 8 catégories avec leurs maladies
  (`code`/`duration_months`/`name`/`description`), extraction directe
  des tableaux du document.
- `disease-subcases.json` : sous-cas des 4 blocages (801-804).
  **Contrairement aux deux fichiers ci-dessus, celui-ci n'est pas une
  extraction purement mécanique** — la source pour 804 (Mariage) est de
  la prose continue, pas une liste structurée ; le découpage en 5
  sous-cas a été fait par interprétation, présenté à l'utilisateur pour
  validation le 2026-08-19. 801/802 sont fiables (format source propre),
  803 est une liste plate sans description.

Si le document source change, re-parser depuis le `.docx` plutôt que
d'éditer ces JSON à la main, pour ne jamais désynchroniser source ↔
seeders.
