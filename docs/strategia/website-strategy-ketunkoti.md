# Verkkosivustrategia: Ketun koti (ketunkoti.fi)

**Painotus:** sähköpostilistan (Ketun kodit) kasvattaminen henkilöillä, jotka ovat kiinnostuneita uudehkoista sijoitusasunnoista.
**Päivitetty:** 24.10.2026
**Lähteet:** ketunkoti.fi:n kaikki 10 julkista sivua (sitemap 24.10.2026), sivujen HTML-lähdekoodi (lomakkeet, meta-tiedot), teeman ja `ketunkoti-features`-pluginin koodi. Ennakkokysymyksiä, positiointityötä, avainsana-analyysiä tai analytiikkadataa ei ollut käytettävissä. Puutteet on merkitty `⚠️ AVOIN`.

Merkinnät:
- `⚠️ AVOIN:` tieto puuttuu tai on ristiriitainen, selvitettävä
- `💡 MEOM SUOSITUS:` analyysin pohjalta tehty ehdotus, ei sivustolta luettu fakta

---

## Tiivistelmä

Sivuston perusidea on vahva: listan jäsenet näkevät uudet sijoitusasunnot ensimmäisinä. Etusivu on rakennettu tämän lupauksen ympärille, ja lomake on heti ensimmäisessä näkymässä. Kolme asiaa heikentää kuitenkin listan kasvua ja luottamusta selvästi enemmän kuin mikään visuaalinen yksityiskohta:

1. **Lupaukset ja todellinen tarjonta ovat ristiriidassa.** Etusivu lupaa uudiskohteita Lahden keskustan kupeesta. Ainoat sijoitusasunnot sivustolla ovat Espoon Finnoossa. Etusivun "positiivinen kassavirta" ei toteudu ainoassa myynnissä olevassa kohteessa: vuokra 675 €/kk ja vastikkeet yhteensä 979,30 €/kk. "Alkaen 26 000 €" on myyntihinta, ja velaton hinta on 170 000 €.
2. **Listan arvoa ei ole konkretisoitu.** Kävijä ei tiedä, mitkä kohteet ovat tulossa, milloin, kuinka kauan listalla on etuaika ja mitä viesti sisältää.
3. **Mittausta ei ole.** Sivustolla ei ole analytiikkaa, ja kaikki lomakkeet lähettävät saman Buttondown-tagin (`avajaiset`). Ei voi tietää, mikä sivu tai kanava tuo tilaajat.

Lisäksi tietosuojaseloste ei mainitse uutiskirjettä eikä Buttondownia. Se jopa sanoo, ettei tietoja luovuteta kolmansille osapuolille. Footerin tietosuojalinkki on rikki (404).

Nopeimmat parannukset: korjaa ristiriidat, kerro tulevista kohteista konkreettisesti, lisää listan tilausmahdollisuus kohdesivuille ja myytyihin kohteisiin, ja ota käyttöön lähdekohtaiset tagit.

---

## Osio 1: Strateginen pohja

### 1.1 Yritys pähkinäkuoressa

Ketun koti on Sami Keijosen yritystoiminnan (Fox Holding Oy -nimen perintöä kantava kettubrändi, Y-tunnus 2227257-6) asuntopuoli. Se myy pääasiassa valmiiksi vuokrattuja sijoitusasuntoja ja satunnaisesti koteja omistusasujille, sekä vuokraa asuntoja. Ketun koti ei ole kiinteistönvälittäjä. Myynti tehdään suoraan, ja uudet sijoitusasunnot on tarkoitus myydä ensin sähköpostilistan tilaajille. Taustalla on vuodesta 2022 kertynyt kokemus asuntosijoittamisesta ja vuokranantajuudesta sekä Sijoitusasuntovahti-palvelun tekeminen.

### 1.2 Tulevaisuuden tavoitteet ja markkinoinnin tavoitteet

Sivustolta luettava tavoite: myydä uudet sijoitusasunnot ensisijaisesti sähköpostilistalle ("Uudet sijoitusasuntomme myydään ensin Ketun kodit -listalle").

⚠️ AVOIN: Tavoitteelle puuttuvat luvut. Kysymykset Samille:
- Kuinka monta sijoitusasuntoa on tulossa myyntiin ja millä aikataululla? (Lahden kohteet)
- Kuinka monta tilaajaa listalla on nyt?
- Mikä on riittävä listan koko, jotta kohde myydään listalle ilman julkista markkinointia? Nyrkkisääntönä voi miettiä: jos 1–3 % aktiivisista tilaajista on ostovalmiita kohteen tullessa, yhden kohteen myymiseen tarvitaan muutamia satoja laadukkaita tilaajia. Tämä on oletus, ei data.

💡 MEOM SUOSITUS: Määritä tavoite muodossa "X tilaajaa ennen ensimmäisen Lahden kohteen julkaisua (pvm)" ja "kohde myyty listalle Y päivässä". Esimerkki: 300 tilaajaa 31.3.2027 mennessä, ja ensimmäinen kohde myydään listalle ennen julkista ilmoitusta. Luvut on sovittava Samin kanssa.

**Verkkosivuston rooli:** kerätä ja lämmittää ostajaehdokkaita ennen kohteiden myyntiä. Sivusto on listan sisäänheittäjä, kohteiden esittelyalusta ja luottamuksen rakentaja (kuka myy, miksi luottaa).

### 1.3 Erottuvuustekijät

| Väite (sivustolta) | Erottaako? | Todiste sivustolla | Arvio |
|---|---|---|---|
| **Ensimmäisenä apajilla:** kohteet listalle ennen muita | Kyllä, jos etuaika on todellinen ja konkreettinen | Ei. Etuajan pituutta tai sitä, mihin kohteet myöhemmin julkaistaan, ei kerrota | Vahvin ydin, mutta abstrakti. Terävöitä: "Kohde on listalla X päivää ennen kuin se julkaistaan muualla." |
| **Ei nettiin, ei hälinää:** ei hintakilpailuja eikä satojen katsojien näyttöjä | Kyllä, suora myyjä vs. välittäjä | Osittain: kohteet ovat kuitenkin sivustolla ja YouTubessa | Ristiriitainen sanamuoto: kohteet ovat netissä. Tarkoittanee "ei Oikotielle/Etuoveen". Sano se suoraan. |
| **Harvoin mutta hyvää:** postia vain, kun on asiaa | Heikko. Lähes jokainen uutiskirje lupaa näin | Ei | Pidä mukana, mutta älä nojaa tähän. |
| **Valmiiksi vuokrattu, omatonttinen, kilpailukykyinen taloyhtiölaina** | Kohdetason ominaisuuksia, eivät brändin | Kohdesivuilla vuokra, laina 0,55 % marginaali | Hyviä myyntiargumentteja, mutta jokainen sijoitusasuntomyyjä voi listata samat. |
| **Sami itse:** entinen matematiikan opettaja, Sijoitusasuntovahdin tekijä, vuokranantaja vuodesta 2022 | Kyllä, ja se on käyttämättä | Vain UKK:n haitarin sisällä | 💡 Ainutlaatuisin erottuvuustekijä. Kukaan muu ei voi sanoa tätä. Sopii täydellisesti lupaukseen "numerot auki". |
| **Persoonallinen, humoristinen ääni** ("Kettu ei jaarittele") | Kyllä, erottuu kasvottomista välittäjistä | Koko sivusto | Toimii, kunhan numerot ovat moitteettomat. Huumori + epätarkat luvut = epäluottamus. |

💡 MEOM SUOSITUS: Rakenna erottuvuus kahden asian varaan: **(1) etuoikeus kohteisiin** ja **(2) matematiikanopettaja laskee numerot auki**. Jälkimmäinen ratkaisee sijoittajan suurimman esteen (voiko myyjän lukuihin luottaa). Se myös antaa syyn tilata listalle, vaikka ei ostaisi heti, koska listalla oppii.

### 1.4 Kohderyhmät

**1. Yksityinen asuntosijoittaja, joka etsii uudehkoa, valmiiksi vuokrattua kohdetta** (ensisijainen, etusivu puhuu hänelle)

- **Keitä he ovat:** sivuston perusteella yksityishenkilöitä, jotka ostavat yhden tai muutaman asunnon. Hintataso (myyntihinta 18 000–26 000 €, velaton 166 000–170 000 €) viittaa sijoittajaan, joka hyödyntää taloyhtiölainaa vipuna.
- **Miksi hyvä kohderyhmä:** pieni oma pääoma, valmis vuokralainen ja suora kauppa ilman välityspalkkiota sopivat aloittelevalle ja kokeneelle sijoittajalle.
- **Mitä tarjota:** etuoikeus kohteisiin, avoimet laskelmat (kassavirta, tuotto eri mittareilla, lainan ehdot) ja suora yhteys omistajaan.
- ⚠️ AVOIN: Ostajadataa ei ole. Kysy: kuka osti myydyn Finnoon yksiön? Ensikertalainen vai kokenut, paikallinen vai muualta? Mistä hän löysi kohteen? Tämä kertoo, kenelle etusivu oikeasti puhuu.

**2. Omistusasuja** (toissijainen, satunnaiset kohteet, kuten Laurinlahden kolmio)

- Erillinen polku "Myytävät kodit" -sivun kautta. Etusivun alalaidassa on oikea ohjaus ("Etsitkö omaa kotia etkä sijoitusta?").
- Lista on suunniteltu sijoittajille, mutta tilaussivu lupaa postia "muistakin Ketun kodeista".

💡 MEOM SUOSITUS: Erottele kiinnostus jo tilausvaiheessa (Buttondownin tagit, esim. valinta "Sijoitusasunnot" / "Kodit omaan käyttöön"). Kodinostajalle ei lähetetä sijoituslaskelmia, eikä sijoittaja kyllästy kotikohteisiin.

**3. Vuokralaiset** (sivuston FAQ:ssa mainittu, ei listan kohderyhmä)

- Vuokra-asuntojen sisältöä ei tällä hetkellä ole sivustolla. Ei vaikuta listaan, mutta Miskan suositus on hyvä luottamustodiste sijoittajallekin ("vuokralaiset viihtyvät").

---

## Osio 2: Verkkosivustrategia

### 2.1 Uudistuksen tavoitteet

Tämä ei ole kokonaisuudistus vaan konversio-optimointi olemassa olevalle, juuri lanseeratulle sivustolle.

💡 MEOM SUOSITUS, ehdotetut projektitavoitteet:
1. Ristiriidat korjattu (sijainti, kassavirta, hinnan esitystapa) ennen kuin listaa markkinoidaan aktiivisesti.
2. Jokaiselta sivulta mitattava tilauspolku (lähdekohtainen tagi + kävijämäärä).
3. Tilauskonversio etusivulla ja sijoitusasuntosivulla mitattu ja tavoite asetettu. ⚠️ Lähtötasoa ei ole, joten ensin mitataan 4–6 viikkoa.

### 2.2 Verkkosivuston tavoitteet

Pysyvä rooli: Ketun kotien **luotettava ja lämmin julkisivu**, joka (1) kerää sijoitusasunnoista kiinnostuneet listalle, (2) esittelee kohteet avoimin numeroin ja (3) rakentaa luottamusta Samiin myyjänä ja vuokranantajana.

### 2.3 Nykyinen sivusto

**Toimii (säilytä):**
- Etusivun hero ja lomake heti ensimmäisessä näkymässä. Otsikko kertoo listan ydinlupauksen.
- Lomake on yksinkertainen (vain sähköposti), ja kitkaa vähentävät mikrotekstit ovat hyvät ("Ei roskapostia, voit perua milloin vain").
- Etusivun FAQ purkaa juuri oikeat esteet (maksaako, sitoudunko, kuinka usein postia tulee, miten peruu).
- Kohdesivujen avainluvut (myyntihinta, velaton hinta, vastikkeet, vuokra, rakennusvuosi) ovat heti näkyvissä.
- Ääni ja kettu-maskotti erottuvat edukseen.
- Kohdesivuilla on esittelyvideo, pohjakuva ja aluekuvaus.

**Ei toimi (korjaa):**

| # | Havainto | Miksi haittaa listaa | Vakavuus |
|---|---|---|---|
| 1 | Etusivu: "Lahden keskustan kupeessa". Sijoitusasunnot-sivun ainoat kohteet ovat Espoossa. | Kävijä, joka klikkaa "Sijoitusasunnot", näkee eri kaupungin. Lupaus vaikuttaa harhaanjohtavalta. | Kriittinen |
| 2 | "Hinnat alkaen 26 000 €" ilman velatonta hintaa (Finnoo: velaton 170 000 €). | Kokenut sijoittaja tunnistaa vivun heti, ja aloittelija voi ymmärtää väärin. Kummassakin tapauksessa luottamus kärsii. ⚠️ Tarkista myös, edellyttääkö asuntokaupan markkinointia koskeva sääntely velattoman hinnan esittämistä yhtä näkyvästi (koskee elinkeinonharjoittajaa, joka myy kuluttajille). | Kriittinen |
| 3 | "Positiivinen kassavirta". Finnoon kulmayksiö: vuokra 675 €, hoitovastike 106,40 € + pääomavastike 872,90 € = −304,30 €/kk ennen veroja. | Väite ei toteudu ainoassa myynnissä olevassa kohteessa. Jos se koskee Lahden kohteita, sitä ei sanota. | Kriittinen |
| 4 | Tuottoluvut sekaisin: etusivu "noin 5 %", kohdesivu "oman pääoman tuotto 13 %". Mittareita ei määritellä. | Sijoittaja ei tiedä, mitä verrata. Teeman tuottolaskenta on nettovuokratuotto velattomasta hinnasta, Finnoossa noin 4,0 %. | Korkea |
| 5 | Tietosuojaseloste ei mainitse uutiskirjettä eikä Buttondownia, ja sanoo "Emme luovuta näitä tietoja kolmansille osapuolille". | GDPR-informointi puutteellinen. Tarkka sijoittaja lukee selosteen. | Korkea |
| 6 | Footerin tietosuojalinkki `/tietosuojaselosta/` palauttaa 404. | Rikkinäinen linkki lomakkeen läheisyydessä syö luottamusta. | Korkea (pieni korjaus) |
| 7 | Kaikki lomakkeet lähettävät tagin `avajaiset`, eikä sivustolla ole analytiikkaa. | Ei tiedetä, mikä sivu, kanava tai some-postaus tuo tilaajat. | Korkea |
| 8 | Kohdesivuilla ja myydyissä kohteissa ei ole listan tilausmahdollisuutta, vain yhteydenottolomake. | Kuumin liikenne (kohteesta kiinnostuneet) ei päädy listalle. Erityisesti myyty kohde on menetetty tilaisuus: "Myöhästyit, seuraava menee ensin listalle." | Korkea |
| 9 | Kohdesivujen kenttäotsikoissa "Pääomavastikevastike" ja "Yhtiövastikevastike". | Pieni, mutta numeroita myyvällä sivulla kirjoitusvirheet heikentävät tarkkuuden vaikutelmaa. | Keskitaso |
| 10 | UKK:n ingressi: "Oikeasti ei ole kysytty vielä mitään... keksitään kysymyksiä itse." | Hauska, mutta kertoo samalla, ettei ostajia vielä ole. Toimii brändille, heikentää sosiaalista todistetta. | Matala, makuasia |
| 11 | Sami esittäytyy vain UKK:n haitarissa. | Vahvin luottamustekijä on piilossa. | Keskitaso |
| 12 | Kuvien alt-tekstit puuttuvat (`alt=""`) myös kohdekuvista. | Saavutettavuus ja kuvahaku. | Matala |

### 2.4 Ostopolut ja konversiopisteet

⚠️ AVOIN: Oikeaa myyntiprosessia ei ole dokumentoitu (miten Finnoon yksiön ostaja löysi kohteen, kauanko kauppa kesti, mitä hän kysyi). Alla oleva polku perustuu sivuston sisältöön ja sijoitusasuntokaupan yleiseen logiikkaan. Validoi seuraavan kaupan yhteydessä.

**Polku: asuntosijoittaja → listan tilaaja → ostaja**

| Vaihe | Kuka | Kysymys | Este | Todiste | CTA | Sisältö |
|---|---|---|---|---|---|---|
| **1. Herätys** | Sijoittaja, joka selaa Oikotietä, sijoitusfoorumeita, Instagramia tai YouTubea | "Löytyisikö kohde, jossa numerot toimivat ilman hintakilpailua?" | Ei tunne Ketun kotia | Lyhyt video tai postaus, jossa yksi konkreettinen laskelma | Seuraa / klikkaa sivustolle | Instagram, YouTube Shorts, ⚠️ hakunäkyvyys selvittämättä |
| **2. Kartoitus** | Sama, sivustolla ensimmäistä kertaa | "Mitä täältä saa ja kannattaako antaa sähköposti?" | Abstrakti lupaus, epäselvä sijainti ja hinta | Tulevien kohteiden konkreettinen esittely, Samin tausta, avoin esimerkkilaskelma | **Tilaa Ketun kodit** (matala kitka) | Etusivu, Sijoitusasunnot-sivu, "Tulossa"-osio |
| **3. Lämmittely** (listalla) | Tilaaja | "Ovatko nämä minulle sopivia kohteita, ja voiko myyjään luottaa?" | Kohteen tuloon voi mennä kuukausia, ja kiinnostus hiipuu | Tervetuloviesti: mitä on tulossa ja milloin, miten laskelmat tehdään, kuka Sami on | Vastaa viestiin / katso esimerkkilaskelma | ⚠️ Tervetuloviestiä ei ole tiedossa |
| **4. Vertailu** | Tilaaja, kun kohde julkaistaan listalle | "Ovatko luvut oikein, ja miten tämä vertautuu muihin?" | Kassavirta, tuotto ja laina epäselviä. Isännöitsijäntodistus puuttuu. | Täydellinen laskelma, taloyhtiön dokumentit, vuokrasopimuksen ehdot | Pyydä isännöitsijäntodistus / esittelymateriaali (keskikitka) | Kohdesivu + ladattavat dokumentit |
| **5. Päätös** | Ostaja (+ mahdollisesti puoliso ja pankki) | "Saanko rahoituksen, ja miten kauppa etenee?" | Rahoitus, kaupan käytäntö ilman välittäjää | Kaupan prosessi selkeästi, kesto, kuka hoitaa mitä | **Ota yhteyttä / varaa esittely** (korkea kitka) | Kohdesivun yhteydenotto, "Näin kauppa etenee" -osio |
| **6. Suosittelu** | Ostaja ja vuokralainen | — | — | Ostajan kommentti, "myyty listalle X päivässä" | Suosittele ystävälle | Myydyt kohteet referensseinä |

### 2.5 Sisältöinventaario ostopoluilla

**Olemassa:**
- Etusivu: lupaus, lomake, kohdetyypin ominaisuudet, "Miksi listalle?" ja FAQ
- Myytävät sijoitusasunnot (2 kohdetta, 1 myyty)
- Kohdesivut: avainluvut, video, pohjakuva, taloyhtiö- ja aluekuvaus
- UKK: Sami, kettu, ostaminen, vuokraaminen, palvelut
- Tilaa Ketun kodit -sivu (vain lomake)
- Instagram ja YouTube (Shorts-videot kohteista)

**Puuttuu (tuotettava):**
1. **"Tulossa"-teaser Lahden kohteista:** kuinka monta, millainen talo, valmistumisaika, arvioitu hintahaarukka (myynti + velaton), milloin listalle. Tärkein yksittäinen puute.
2. **Avoin esimerkkilaskelma:** kassavirta ennen veroja ja jälkeen, tuotto velattomasta hinnasta, oman pääoman tuotto, pääomavastikkeen vaikutus, lainaosuuden poismaksun vaikutus. Toimii myös liidimagneettina.
3. **"Kuka on kettu" -nosto** etusivulle (kuva, 2–3 lausetta, linkki).
4. **"Näin kauppa etenee ilman välittäjää"** -osio: luottamus ja rahoitus.
5. **Listan tervetuloviesti ja -sarja** Buttondownissa (⚠️ nykytila tuntematon).
6. **Myytyjen kohteiden tarina:** "Myyty listalle" + kuinka nopeasti (kun dataa on).

### 2.6 Call-to-Action -suunnitelma

**Matala kitka (pääpaino):**

| CTA | Sijainti | Mittari | Tavoite |
|---|---|---|---|
| "Tilaa Ketun kodit" (nykyinen lomake) | Etusivun hero, Sijoitusasunnot-sivu, Tilaa-sivu, footer | Buttondown-tilaus, sivukohtainen tagi (esim. `etusivu`, `sijoitusasunnot`, `kohde-finnoo`, `myyty`, `instagram`) | ⚠️ Mitataan lähtötaso 4–6 vk |
| 💡 "Myöhästyitkö? Seuraava kohde menee ensin listalle" | Myydyn kohteen sivu (korvaa/täydentää yhteydenottolomakkeen) | Tagi `myyty` | — |
| 💡 Kevyt tilauslaatikko kohdesivun avainlukujen alla | Kaikki kohdesivut | Tagi `kohde-[slug]` | — |
| 💡 "Lisää minut myös Ketun kodit -listalle" -rasti | Yhteydenottolomakkeet | Contact Form 7 → Buttondown (manuaalinen tai integraatio) | — |
| Seuraa Instagramissa / YouTubessa | Footer (on jo) | Klikit (vaatii analytiikan) | — |

**Keskikitka:**

| CTA | Sijainti | Mittari |
|---|---|---|
| 💡 "Lataa esimerkkilaskelma" (vastineeksi sähköposti = listalle tagilla `laskelma`) | Etusivu, Sijoitusasunnot, UKK | Tilaukset tagilla `laskelma` |
| 💡 "Pyydä isännöitsijäntodistus ja kohteen dokumentit" | Kohdesivut | Lomakeviestit |

**Korkea kitka:**

| CTA | Sijainti | Mittari |
|---|---|---|
| "Ota yhteyttä" / "Sovitaan esittely" (nykyinen) | Kohdesivut, Ota yhteyttä -sivu | Contact Form 7 -lähetykset |

💡 MEOM SUOSITUS: Älä lisää etusivulle kilpailevia CTA:ita. Etusivun ainoa päätavoite on tilaus. Keskikitkan laskelma toimii samalla tilauksena, joten se tukee samaa tavoitetta.

---

## Osio 3: Brändi-identiteetti

### 3.1 Yleistä brändistä
Lämmin, kotoisa ja leikkisä. Kettu-maskotti tuo persoonaa (vrt. animoitu kettu tilauslomakkeessa). ⚠️ Brand bookia ei ole dokumentoitu. Teeman tiedostot toimivat tällä hetkellä brändin lähteenä.

### 3.2 Logo
Kettu-tunnus + "Ketun koti" -teksti, vaalea ja tumma versio (`assets/images/brand/`). Säilyy.

### 3.3 Typografia
Otsikot Cormorant Garamond (serif), leipäteksti Hanken Grotesk. Molemmat Google Fonts, paikallisesti ladattuna, ei lisenssiongelmaa.

### 3.4 Värimaailma
Lämmin beige ja kettu-ruskeat (logossa #B58A63, #A27A55, #5C4632, #F5EFE2, #2E2419). Tarkat tokenit `theme.json`issa.

### 3.5 Kuvamaailma
Valoisat, ammattimaiset sisäkuvat kohteista ja pystyvideot (Shorts). 💡 Lisää Samista aito henkilökuva luottamusnostoihin.

### 3.6 Graafiset elementit
Kettu-kuvitus, animoitu kettu lomakkeessa, "KETUN KOTI" -videoteksti footerissa (työn alla).

### 3.7 Tone of voice
Minä-muotoinen, humoristinen, rehellinen ("Kettu ei jaarittele", "kettu ei loukkaannu (paljon)"). Vahvuus, kunhan numerot ovat täsmällisiä.
💡 Linjaus: **huumori tekstissä, ei koskaan numeroissa.** Laskelmissa ja ehdoissa asiallinen, tarkka ja täydellinen.

### 3.8 Esimerkit ja benchmarkit
Aiemmin tässä projektissa kartoitettuja: The Modern House ja Fantastic Frank (toimituksellinen kohde-esittely), Norm Architects (lämmin pohjoismainen kuvamaailma). ⚠️ Sijoitusasuntopuolen suomalaisia vertailukohtia ei ole kartoitettu.

---

## Osio 4: Kevyt sisältökonsepti

### 4.1 Sisältöinventaario
- **Säilytetään:** etusivun rakenne, FAQ, kohdesivupohja, UKK, Tilaa-sivu
- **Korjataan:** sijainti-, hinta-, kassavirta- ja tuottoväitteet; tietosuojaseloste; footer-linkki; kenttäotsikot
- **Uutta:** Tulossa-teaser, esimerkkilaskelma, Kuka on kettu -nosto, kaupan kulku -osio, myydyn kohteen listaohjaus

### 4.2 Sivustorakenne ja navigaatio

Nykyinen päävalikko toimii. Ehdotus pienin muutoksin:

```
Sijoitusasunnot      (myynnissä + tulossa + myydyt)
Myytävät kodit
Näin kettu laskee    (uusi: esimerkkilaskelma + mittarit selitettynä)
UKK
Ota yhteyttä
[Tilaa Ketun kodit]  (napin muotoinen, kuten nyt)
```

💡 "Näin kettu laskee" kantaa erottuvuustekijää (matematiikanopettaja, avoimet numerot) ja on luonteva hakukoneiden sisäänheittosivu.

### 4.3 Leiskattavat sivupohjat

| Pohja | Tila | Perustelu |
|---|---|---|
| Etusivu | On, päivitetään | + Tulossa-teaser, Kuka on kettu, korjatut väitteet |
| Sijoitusasunnot (listaus) | On, päivitetään | + Tulossa-osio ja tilauslaatikko myytyjen kohteiden jälkeen |
| Kohdesivu | On, päivitetään | + tilauslaatikko, laskelma-osio, dokumentit, myydylle oma CTA |
| Näin kettu laskee | Uusi | Erottuvuus + liidimagneetti |
| Tilaa Ketun kodit | On, päivitetään | Nyt pelkkä lomake. Lisää 3 syytä, esimerkkiviesti ja Samin esittely. |
| UKK, Ota yhteyttä, Tietosuoja | On | Sisältökorjaukset |

Kaikki toteutettavissa nykyisillä blokeilla ja pattern-kirjastolla (sivusto on block theme, ei kala-stack/meomblocks).

### 4.4 Pääviestit sivustolla

1. **Uudet sijoitusasunnot listalle ensin.** (Konkretisoi etuaika ja tulevat kohteet.)
2. **Numerot auki, laskettuna matematiikanopettajan tarkkuudella.** (Myyntihinta, velaton hinta, kassavirta, tuotto, mittarit määriteltyinä.)
3. **Suoraan omistajalta: ei välittäjää, ei hintakilpailua, valmiiksi vuokrattu.**
4. **Kettu on oikea ihminen:** Sami, vuokranantaja vuodesta 2022, vastaa vuorokauden sisällä.

---

## Osio 5: Tekniikka

### 5.1 Tekniset huomiot

**Must have:**
- **Lähdekohtaiset Buttondown-tagit:** tilauslomake on synkronoitu pattern (blokki 46), jossa kiinteä `tag=avajaiset`. 💡 Muuta lomake niin, että tagi tulee sijainnista: esimerkiksi oma pieni lomakeblokki tai render-filtteri, joka lisää sivun slugin tagiksi. Muussa tapauksessa tarvitaan useita synkronoituja kopioita.
- **Evästeetön analytiikka** (esim. Plausible tai Matomo ilman evästeitä): sivun kävijät, tilauskonversio sivukohtaisesti, UTM-seuranta somesta. Linjassa nykyisen "ei evästeitä" -lupauksen kanssa.
- **Tietosuojaselosteen päivitys:** Buttondown käsittelijänä, käsittelyperuste (suostumus), tiedonsiirto EU:n ulkopuolelle, säilytysaika, peruminen.
- **Footer-linkin korjaus** `/tietosuojaselosta/` → `/tietosuojaseloste/`.
- **Kenttäotsikkojen korjaus** ("Pääomavastikevastike", "Yhtiövastikevastike").

**Jatkokehitys:**
- Kassavirtalaskuri kohdesivulle (syötä oma laina ja vero, näe kassavirta). Laskelma on jo osin pluginissa (`bindings.php`: tuottolaskenta).
- Tervetulosarja Buttondownissa (2–3 viestiä).
- Contact Form 7 → Buttondown -suostumusrasti.

### 5.2 Sisältötyypit (WordPress)

Nykyinen toteutus riittää: CPT `home` (kodit) + taksonomiat + meta (hinnat, vastikkeet, vuokra) + block bindings.

💡 Tila "Tulossa" (nykyisten Vapaa/Myyty rinnalle) mahdollistaa teaser-kohteet samalla sivupohjalla, ilman erillistä sisältötyyppiä.

---

## Osio 6: Avoimet asiat ja seuraavat askeleet

### 6.1 Puuttuvat tiedot (kysytään Samilta)
1. Lahden kohteet: määrä, talo, valmistuminen, hintahaarukka (myynti + velaton), milloin listalle. Ilman näitä etusivun lupaus jää abstraktiksi.
2. Mihin "positiivinen kassavirta" ja "noin 5 % tuotto" viittaavat: Lahden kohteisiin? Millä oletuksilla?
3. Listan nykyinen koko ja tilaajakehitys.
4. Etuajan pituus: kuinka kauan kohde on vain listalla ennen julkista julkaisua, ja missä se julkaistaan sen jälkeen?
5. Myydyn Finnoon yksiön ostajan polku (mistä löysi, mitä kysyi).
6. Tervetuloviestin nykyinen sisältö Buttondownissa.

### 6.2 Päätökset joita tarvitaan
1. Pysyykö Espoo mukana sijoituskohteissa, vai keskittyykö lista Lahteen? Viestin pitää kattaa molemmat ("Etelä-Suomen kasvukeskuksissa") tai olla kohdekohtainen.
2. Mikä tuottomittari on pääluku? 💡 Suositus: näytä aina kolme määriteltynä: nettovuokratuotto velattomasta hinnasta, kassavirta €/kk ja oman pääoman tuotto.
3. Kerätäänkö kiinnostus erikseen sijoittajille ja kodinostajille (tagit)?
4. Liidimagneetti (esimerkkilaskelma) kyllä/ei.

### 6.3 Tarvittavat materiaalit
- Samin henkilökuva
- Lahden kohteiden kuvat tai havainnekuvat (jos saatavilla) ja perustiedot
- Esimerkkilaskelman pohja (Excel/Sheets)
- Isännöitsijäntodistukset ja muut ladattavat kohdedokumentit

### 6.4 Seuraavat askeleet
1. **Viikko 1, korjaukset:** ristiriidat (sijainti, kassavirta, velaton hinta), tietosuojaseloste, footer-linkki, kenttäotsikot.
2. **Viikko 1–2, mittaus:** lähdekohtaiset tagit + evästeetön analytiikka. Lähtötaso talteen.
3. **Viikko 2–4, sisältö:** Tulossa-teaser, Kuka on kettu, tilauslaatikot kohde- ja myytyihin sivuihin, tervetuloviesti.
4. **Kuukausi 2:** Näin kettu laskee -sivu ja esimerkkilaskelma liidimagneettina.
5. **Jatkuva:** some ohjaa tagitetuille sivuille, tilaajamäärää seurataan tavoitetta vasten.
6. Jos haluat syventää: `content-concept`-skilli (sivukohtaiset tekstiluonnokset) ja `seo-audit` (hakukysyntä esim. "sijoitusasunto Lahti").

---

## QA-yhteenveto
- **Tarkistetut lähdemateriaalit:** ketunkoti.fi kaikki 10 sivua sitemapista (etusivu, Sijoitusasunnot, Tilaa, Myytävät kodit, UKK, Ota yhteyttä, 3 kohdesivua, tietosuojaseloste); lomakkeiden HTML (Buttondown-action, hidden-tagi `avajaiset`); footer-linkin HTTP-tila (404); analytiikkaskriptit (ei löytynyt); `ketunkoti-features`-pluginin meta- ja tuottolaskentakoodi; teeman brändiassetit.
- **Löydetyt ja korjatut ongelmat:** kassavirta laskettiin uudelleen sivun luvuista (675 − 106,40 − 872,90 = −304,30 €/kk). Tuottomittari tarkistettiin pluginin kaavasta ennen kuin siihen viitattiin. Listan tilaajamäärästä tai ostajista ei esitetty lukuja.
- **Avoimiin asioihin siirretyt:** Lahden kohteiden tiedot, kassavirta- ja tuottoväitteiden perusta, listan koko, etuajan pituus, ostajadata, tervetuloviestin sisältö, hakukysyntä, velattoman hinnan esittämisvelvollisuus markkinoinnissa.

## QA-kerros
- Tarkistettu: strategia-tarkistuslistan seitsemän kohtaa; jokainen sivustoa koskeva väite jäljitettiin 24.10.2026 haettuun HTML:ään (10 sivua) tai projektin koodiin; laskelmat (kassavirta, tuotto) laskettiin kohdesivun luvuista ja pluginin kaavasta.
- Korjattu: tilaajamäärät, ostajaprofiilit ja tavoiteluvut jätettiin pois keksittyinä ja siirrettiin avoimiksi; "noin 5 %" ja "13 %" erotettiin eri mittareiksi sen sijaan, että toinen olisi tulkittu virheeksi; omat ehdotukset merkittiin 💡-merkinnällä.
- Epävarmaa: (1) koskeeko "positiivinen kassavirta" Lahden tulevia kohteita, jolloin havainto 3 lievenee; (2) velattoman hinnan esittämisvelvollisuus markkinoinnissa on tarkistettava juridisesti, sitä ei verifioitu; (3) listan tilaajamäärä ja Buttondownin tervetuloviesti eivät näy julkisesti; (4) listan kokotavoitteen nyrkkisääntö on oletus, ei dataa; (5) hakukysyntää ei mitattu.
- Vaikutusluokka: korkea — dokumentti sisältää hintoja, laskelmia ja väitteitä sivuston nykytilasta, ja sen pohjalta muutetaan myyntiväitteitä, joilla on kuluttajansuojallista merkitystä.
