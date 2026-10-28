# Devesh Studio – Client Album (Guide)

Har wedding client ke liye ek **private album page** banta hai. Client apne alag username aur password se login karta hai. Login ke baad use dikhta hai:

1. **Banner**: couple ka naam, cover photo, shaadi ki date, venue aur events (Haldi, Mehendi…)
2. **PDF album, book jaisa**: pages palat kar dekhne wala flipbook (zoom, full screen, page palatne ki awaaz)
3. **Films**: YouTube/Vimeo link ya MP4 upload
4. **Photos**: event ke hisaab se tabs, bade photo, slideshow, download (agar aap allow karein)

Aap sab kuch **admin panel** (`admin.php`) se karte ho, coding ki zaroorat nahi.

---

## Folder mein kya hai

`website-files` folder ke andar ki saari cheezein website par upload karni hain:

| File / Folder | Kaam |
|---|---|
| `client-login.php` | Client login page |
| `album.php` | Client ka album page |
| `admin.php` | Aapka admin panel |
| `media.php` | Photos/PDF/films sirf login ke baad bhejta hai |
| `logout.php` | Logout |
| `album-core/` | Settings (`config.php`) aur program code |
| `album-assets/` | Design: CSS, JS, toran/mandala images, watermark |
| `clients/` | Har client ka folder (photos, PDF, films). Ye **private** hai |

---

## 1. Website par upload kaise karein (Hostinger)

1. hPanel kholo → **Websites → Manage → File Manager** → `public_html` folder (jahan `index.html` hai).
2. **`website-files.zip`** (is folder mein ready hai) ko `public_html` mein upload karo, phir us zip par right-click karke **Extract** karo.
   Extract ke baad `public_html` mein `client-login.php`, `album.php`, `admin.php`… aur `album-core`, `album-assets`, `clients` folders dikhne chahiye. Zip file ab delete kar sakte ho.
3. Dhyan rahe: `clients/.htaccess` aur `album-core/.htaccess` hidden files hain (zip mein hain). Yahi photos ko private rakhti hain.
   Aapki purani files (index.html waghera) par koi asar nahi padta, sirf nayi files judti hain.

## 2. Website menu mein "Client Login" jodna

Ye 7 pages kholo: `index.html`, `about.html`, `services.html`, `gallery.html`, `masonry.html`, `feedback.html`, `contact.html`.
Har page mein Contact wali menu line dhundo:

```html
<li><a href="contact.html">Contact</a></li>
```

(`contact.html` mein ye `<li class="active">` ho sakti hai.) Uske **theek upar** ye line paste karo:

```html
<li><a href="client-login.php">Client Login</a></li>
```

## 3. Admin password banana (sirf pehli baar)

Upload ke **turant baad** `https://deveshstudio.com/admin.php` kholo aur apna admin password banao (kam se kam 8 akshar).
Is page ka address kisi ko mat dena.

Dashboard par hara (green) message **"Client files are private"** dikhna chahiye. Agar laal (red) warning dikhe, to `clients/.htaccess` dobara upload karo.

## 4. Naya client kaise add karein

1. `admin.php` → **New client**
2. Dulhan, dulhe ka naam, date, venue, city aur events bharo
3. Username likho (jaise `sunitaamit`). Password ke liye **Make one** dabao
4. **Create client** dabao. Ek card aayega jismein login details hongi, wahan **Send on WhatsApp** dabakar couple ko bhej do
   (password sirf isi samay dikhta hai)
5. Ab neeche upload karo:
   - **Cover photo**: banner ki badi photo (landscape photo achhi lagti hai)
   - **PDF album**: badi file bhi chalegi, apne aap tukdon (pieces) mein upload hoti hai
   - **Photos**: pehle event chuno (Haldi, Wedding…), phir bahut saari photos ek saath daalo
   - **Films**: YouTube/Vimeo link daalo, ya MP4 upload karo (film ki cover photo apne aap ban jaati hai)
6. **Preview album** dabakar dekh lo ki sab theek hai

**Album band karna:** client page par **Close album** dabao, client login nahi kar payega.
**Password bhool gaye:** client page par naya password daalkar Save karo.

## 5. Achhe result ke liye tips

- **Photos:** JPG, 2000–2500 px chaudi (1–3 MB). Upload ke baad chhoti copies apne aap banti hain, isliye page fast khulta hai.
- **PDF album:** Agar PDF ke pages "spread" hain (do page ek saath, chaudi page), to wo apne aap beech se katkar asli book jaise dikhte hain. PDF ka pehla page cover ho to wo band kitaab jaisa dikhta hai.
- **Films:** Lambi films ke liye YouTube par **Unlisted** upload karke link daalo. Isse server par load nahi padta.
- **Downloads:** "Allow downloads" on karoge tabhi client photos, PDF aur films download kar payega.
- **Watermark:** "Devesh Studio" watermark bade photos par lagta hai. Apna logo lagana ho to `album-assets/img/watermark.png` ko apne transparent PNG logo se badal do.

## 6. Bina admin ke (File Manager / FTP se) client banana

1. `clients/priya-rahul` folder copy karo aur naam badlo (chhote akshar aur dash, jaise `sunita-amit`)
2. `data.json` mein details badlo. Password ke liye `"password": "naya-password"` likh do.
   Pehli baar page khulte hi ye apne aap secure code (hash) mein badal jaata hai, plain password file mein nahi rehta.
3. Photos ko `photos/haldi`, `photos/wedding`… folders mein daalo. PDF ka naam `album.pdf` rakho
4. Admin mein us client par jaakar **Prepare photos for fast loading** dabao

## 7. Sample client (testing ke liye)

| | |
|---|---|
| Login page | `https://deveshstudio.com/client-login.php` |
| Username | `priyarahul` |
| Password | `gulab-sitara-4826` |

Isme demo photos, demo PDF album aur aapke YouTube channel ki ek film hai.
**Testing ke baad** admin se ise **Delete** ya **Close album** kar dena.

## 8. Settings badalna

`album-core/config.php` kholo. Isme ye cheezein hain:

- `whatsapp_number`: abhi `919799016153` hai. Check kar lo ki yahi aapka WhatsApp number hai
- Studio ka naam, logo, Instagram/Facebook/YouTube links
- `session_timeout_minutes` (2 ghante), `max_login_attempts` (5), `lockout_minutes` (10)

## 9. Security, kya-kya protection hai

- Photos, PDF aur films ka direct link nahi khulta, sirf login ke baad (`.htaccess` + `media.php`)
- Har client ko sirf **apna** album dikhta hai
- Passwords secure hash mein save hote hain, plain text mein nahi
- 5 galat password daalne par 10 minute ke liye lock
- 2 ghante kuch na karne par apne aap logout
- Google in pages ko search mein nahi dikhata (noindex). Inpar AdSense ads bhi nahi aate

## 10. Koi problem aaye to

| Problem | Kya karein |
|---|---|
| Dashboard par laal warning "files can be opened directly" | `clients/.htaccess` upload karo |
| Photos dheere khul rahe hain | Admin → Settings mein "Photo resizing (GD)" dekho. Off ho to hPanel → PHP Configuration → `gd` on karo |
| Upload beech mein ruk gaya | Internet check karke dobara upload karo |
| Client login nahi kar pa raha | Username chhote aksharon mein likhein. Zaroorat ho to naya password set karo |

---

### Zaroori (alag issue)

Live `contact.html` ke source code mein ek Gmail account ka password khula likha hai (`Email.send({...})` wala code).
Koi bhi "View Source" karke use dekh sakta hai. Us Gmail ka password turant badlo aur ye code page se hata do.
