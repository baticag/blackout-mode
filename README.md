# blackout-mode
Plugin za WordPress.
Zamenjuje sve stranice sajta stranicom sa logoom i tekstom u određenom vremenskom periodu.

## Instalacija
1. Preuzmete .zip
2. Uploadujete preko Plugins stranice i omogućite
3. Uključite rad u podešavanjima plugina (Settings → Blackout Mode) i izmenite podešavanja po želji

<img width="1277" alt="Screenshot 2025-01-23 at 12 03 43" src="https://github.com/user-attachments/assets/ef688f7e-3086-4a93-af34-d3a3515e5834" />

## Mogućnosti
- **Raspored:** početak i kraj u vremenskoj zoni sajta (Settings → General). Prazan početak znači „odmah“, prazan kraj znači „dok se ne isključi“.
- **Sadržaj:** proizvoljan tekst (editor) i logo (podrazumevano logo iz teme).
- **Izgled:** boja pozadine, teksta, naslova i linkova, font (sistemski ili Google Font), osnovna veličina fonta, maksimalna širina sadržaja i polje za dodatni CSS.
- **Preview:** dugme na stranici podešavanja prikazuje blackout stranicu sa sačuvanim podešavanjima, bez obzira na to da li je uključena.
- **Administratori** mogu da vide normalan sajt dok je blackout aktivan (opcija), a u admin baru vide upozorenje „Blackout active“.

### Dodatni CSS
Stranica koristi CSS promenljive koje možete pregaziti u polju „Custom CSS“:

```css
:root {
  --blackout-bg: #000;
  --blackout-text: #fff;
  --blackout-heading: #fff;
  --blackout-link: #bbb;
  --blackout-font: system-ui, sans-serif;
  --blackout-font-size: 18px;
  --blackout-width: 720px;
}
```

Selektori: `body`, `.blackout-content`, `.blackout-logo`, `.blackout-text`.

## Tehničke napomene
- Blackout stranica se šalje sa HTTP statusom **503** i `Retry-After` zaglavljem, tako da je pretraživači tretiraju kao privremenu nedostupnost i ne indeksiraju je umesto pravog sadržaja.
- Šalju se `no-cache` zaglavlja i postavlja se `DONOTCACHEPAGE`, pa je većina caching pluginova neće keširati. Ako koristite keš na nivou servera ili CDN-a (Cloudflare, Varnish, LiteSpeed…), očistite keš kada blackout počne i kada se završi.
- `robots.txt`, `wp-admin`, `wp-login.php` i REST API rade normalno.
- Brisanjem plugina briše se i njegovo podešavanje.

## Licenca
Možete modifikovati kako vam je volja, svakako je brzinski napravljeno.
Poželjno ako napravite nešto što ima smisla i za druge, commitujte ovde 🤓
