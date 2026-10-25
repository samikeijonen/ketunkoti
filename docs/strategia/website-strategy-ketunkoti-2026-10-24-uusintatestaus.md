# Verkkosivustrategia, uusintatestaus: Ketun koti (ketunkoti.fi)

**Painotus:** sähköpostilistan (Ketun kodit) kasvattaminen uudehkoista sijoitusasunnoista kiinnostuneilla.
**Testattu:** 24.10.2026 noin klo 21.30, tuotanto (https://ketunkoti.fi/).
**Vertailukohta:** `website-strategy-ketunkoti.md` (sama päivä, ennen muutoksia).
**Lähteet:**
- tuotannon 10 sivua sitemapista (HTML-lähdekoodi, meta- ja og-tiedot, lomakkeet, schema)
- `robots.txt`, `llms.txt` ja `sitemap.xml`
- Lighthouse mobiili etusivulle
- teeman ja pluginin koodi

Ennakkokysymyksiä, analytiikkaa tai tilaajadataa ei ollut käytettävissä.

Merkinnät:
- `⚠️ AVOIN:` tieto puuttuu tai on ristiriitainen
- `💡 MEOM SUOSITUS:` analyysin pohjalta tehty ehdotus, ei sivustolta luettu fakta

Tämä dokumentti kattaa muutokset ja uudet havainnot. Osiot, joihin ei tullut muutoksia (brändi, sivupohjat, sisältötyypit), on lueteltu lyhyesti, ja niiden osalta pätee edellinen versio.

---

## Tiivistelmä

Etusivu on nyt selvästi parempi listan sisäänheittäjä:
- Lupaus on terävä ("myydään vain Ketun kodit -listalle").
- Hinta näytetään rehellisesti, myynti- ja velattomana.
- Tuleva kohde on konkretisoitu (kaksiot 44,5–46,5 m², oma tontti, vuokrattu).
- Sami esittäytyy etusivulla.

Edellisen testin 12 havainnosta 5 on korjattu, 3 osittain korjattu ja 4 ennallaan.

Kolme asiaa estää yhä listan kasvun tehokkaan mittaamisen ja luottamuksen. Kaikki kolme kannattaa hoitaa ennen arviolta 15.11. lähtevää kohdeviestiä:

1. **Tietosuojaseloste on ristiriidassa uutiskirjeen kanssa.**
   - Seloste sanoo "Emme luovuta näitä tietoja kolmansille osapuolille", eikä mainitse Buttondownia tai uutiskirjettä.
   - Lomakkeen alla on linkki juuri tähän selosteeseen.
   - Nyt kun lista on sivuston päätavoite, tämä on suurin yksittäinen riski. Sekä GDPR-informointi että tarkan sijoittajan luottamus kärsivät.
2. **Mittausta ei ole lainkaan.**
   - Analytiikkaa ei ole.
   - Tuotannon tilauslomakkeissa ei ole enää edes piilokenttää `tag`. Edellisessä testissä kaikilla lomakkeilla oli `avajaiset`.
   - Siksi ei voi tietää, tuleeko tilaaja etusivulta, sijoitussivulta, Instagramista vai YouTubesta.
3. **Ajankohtaa ei kerrota missään.**
   - Etusivulla ei mainita marraskuuta, eikä vastaväitteitä käsitellä (maksaako, sitoudunko, kuinka usein postia tulee).
   - Localhostissa tehty "Hyvä tietää ennen tilaamista" -osio ratkaisee tämän, mutta se ei ole tuotannossa.

---

## Osio 1: Strateginen pohja (muutokset)

### 1.1 Yritys pähkinäkuoressa
Ei muutosta. Myyntimalli on tarkentunut: uudet sijoitusasunnot myydään **vain** listalle, eivätkä ne tule Oikotielle tai Etuoveen. Seuraava erä on muutama kaksio Lahden keskustan kupeessa.

### 1.2 Tavoitteet
Uusi fakta: kohdeviesti lähtee arviolta 15.11.2026, eli noin kolmen viikon päästä.

⚠️ AVOIN: Listalle ei ole vieläkään lukutavoitetta. Kolmen viikon ikkunassa tavoitteen pitää olla lyhyt ja konkreettinen.

💡 MEOM SUOSITUS: Määritä tavoite muodossa "X tilaajaa 15.11. mennessä" ja "kohteet myyty listalle Y päivässä viestistä". Ilman mittausta (osio 5) kumpaakaan ei voi seurata.

### 1.3 Erottuvuustekijät, tilanne nyt

| Väite | Edellinen arvio | Nyt |
|---|---|---|
| Etuoikeus kohteisiin | Abstrakti | **Terävöitynyt:** "vain listalle", "ei Oikotielle, Etuoveen tai muuallekaan". Lisäksi tuleva kohde on kuvattu konkreettisesti. |
| Ei nettiin, ei hälinää | Ristiriitainen | **Korjattu**, mutta kortin otsikko "Vain tilaajille" ja teksti "*Ensimmäiset* kohteet esitellään vain listalle" vetävät eri suuntiin. Myös kielioppi on kesken (ks. 2.3). |
| Harvoin mutta hyvää | Heikko | Kortin teksti ("Saat jatkossakin… tiedot suoraan sähköpostiisi") ei enää lunasta otsikon "harvoin"-lupausta. |
| Sami itse | Piilossa UKK:ssa | **Korjattu:** "Kuka on Ketun kodin takana?" on etusivulla kuvan kanssa. |
| Numerot auki (matematiikanopettaja) | Käyttämättä | Ennallaan. "Entinen matematiikan opettaja" mainitaan, mutta laskelmaa tai mittarin määritelmää ei ole. |

### 1.4 Kohderyhmät
Ei muutosta. Ensisijainen kohderyhmä on yksityinen asuntosijoittaja, joka käyttää taloyhtiölainaa vipuna. Tilaa-sivu lupaa edelleen postia "muistakin Ketun kodeista", joten kodinostajat ja sijoittajat menevät samaan listaan ilman erottelua.

---

## Osio 2: Verkkosivustrategia (muutokset)

### 2.3 Nykyinen sivusto: edellisen testin havaintojen tila

| # | Edellinen havainto | Tila 24.10. illalla | Huomio |
|---|---|---|---|
| 1 | Lahti etusivulla, vain Espoo-kohteita sijoitussivulla | ✅ Korjattu pääosin | Lahti on nyt "seuraavat kohteet", ja Espoo on otsikon "Muut myynnissä olevat sijoitusasunnot" alla. |
| 2 | Velaton hinta puuttui | ✅ Korjattu | Myynti- ja velaton hinta ovat listassa sekä etusivulla että sijoitussivulla. |
| 3 | "Positiivinen kassavirta" ei toteudu Espoon kohteessa | ◐ Lieventynyt | Väite liittyy nyt Lahden kohteisiin. Espoon kulmayksiö (vuokra 675 €, vastikkeet 979,30 €/kk) on silti samalla sivulla heti kriteerilistan alla. |
| 4 | Tuottomittari määrittelemättä | ✖ Ennallaan, ja lukuja on nyt kolmea eri muotoa | Etusivu ja meta: "noin 5 %". Sijoitussivu: "5 % tuotto". `llms.txt`: "noin 5–6 %:n vuokratuotto". Kohdesivu: "oman pääoman tuotto 13 %". |
| 5 | Tietosuojaseloste ei mainitse uutiskirjettä | ✖ Ennallaan | Päivitetty 13.9.2026. Seloste sanoo yhä "Emme luovuta…" ja ohjaa "tällä sivulla olevaan lomakkeeseen", jota sivulla ei ole. |
| 6 | Footerin tietosuojalinkki 404 | ✅ Korjattu | |
| 7 | Sama tagi kaikissa lomakkeissa, ei analytiikkaa | ✖ Heikentynyt | Lomakkeissa ei ole tagia ollenkaan, eikä analytiikkaa ole. |
| 8 | Kohdesivuilla ja myydyissä kohteissa ei tilausmahdollisuutta | ✖ Ennallaan | Kohdesivuilla on vain yhteydenottolomake (Contact Form 7). |
| 9 | "Pääomavastikevastike" | ✅ Korjattu | |
| 10 | UKK:n ingressi "ei ole kysytty vielä mitään" | ◐ Ennallaan | Makuasia. UKK:ssa ei ole yhtään listaa koskevaa kysymystä (localhostissa on). |
| 11 | Sami vain UKK:ssa | ✅ Korjattu | |
| 12 | Alt-tekstit | ◐ | Lighthouse ei löydä virheitä etusivulta. Kohdekuvia ei tarkistettu erikseen. |

**Uudet havainnot:**

| # | Havainto | Miksi haittaa | Vakavuus |
|---|---|---|---|
| U1 | Etusivulla ei kerrota, *milloin* kohteet tulevat myyntiin. | Kiireellisyys puuttuu. "Joskus tulossa" ei saa tilaamaan nyt. | Korkea |
| U2 | Etusivulla ei käsitellä yhtään vastaväitettä (maksaako, sitoudunko, kuinka usein, miksi ei netissä). Ainoa vastaus on mikroteksti "Ei roskapostia, voit perua milloin vain". | Sijoittaja, joka harkitsee, ei saa vastauksia samalla sivulla. | Korkea (ratkaisu valmiina localhostissa) |
| U3 | **og:title** "Ketun koti myy ja vuokraa kauniita koteja" ja **og:description** ("Myydään ja vuokrataan…") ovat vanhan viestin mukaisia. | Some on päälähde (Instagram, YouTube). Jaettu linkki ei kerro listalupauksesta mitään. | Korkea (pieni korjaus) |
| U4 | Meta description: "kilpailukykyisellä taloyhtiö**laina**". | Kirjoitusvirhe hakutuloksessa. Numeromyyjälle tarkkuus on brändiä. | Keskitaso |
| U5 | Kortti "Ei nettiin, ei hälinää": "Kohteet eivät tule Oikotiellä, eikä Etuoveen, tai muuallekaan." | Kielioppi: "Kohteet eivät tule Oikotielle, Etuoveen eivätkä muuallekaan." | Keskitaso |
| U6 | Otsikko "Mitä Ketun koti tarjoaa **avajaisten kunniaksi**?" | "Avajaisia" ei selitetä missään. Kävijä ei tiedä, mitkä avajaiset. | Matala–keskitaso |
| U7 | Tilaa-sivun meta description: "suottaapi… tulla joskus **sähäköpostia**". Leipätekstissä lukee oikein "sähköpostia". | Kirjoitusvirhe tai murrevitsi, joka näkyy hakutuloksessa ilman kontekstia. | Matala |
| U8 | Tilaa-sivu on edelleen pelkkä lomake. | Somesta suoraan tälle sivulle tuleva kävijä ei näe hintoja, kohdetta, aikataulua eikä Samia. | Keskitaso |
| U9 | `llms.txt` (päivitetty 12.10.) kuvaa vanhaa mallia. Sijoitussivu on siinä "Tärkein sivu", tuotto on "5–6 %", eikä "vain listalle" -mallia tai seuraavia kohteita mainita. | AI-hakukoneet ja assistentit toistavat vanhentunutta tietoa. | Keskitaso |
| U10 | Sijoitussivun lista: "loistava sijainti" heti "Lahden keskustan kupeessa" -lauseen jälkeen, ja "5 %" ilman sanaa "noin". | Toisteinen, ja lukumuoto eroaa etusivusta. | Matala |
| U11 | H1 lupaa "uudet sijoitusasuntomme myydään vain listalle", mutta sijoitussivulla on julkisesti myynnissä Espoon kohde. | Pieni ristiriita: Espoo on 2022 valmistunut eli "uudehko". Lukija voi ihmetellä, miksi tämä on julkinen. | Matala |

### 2.4 Ostopolku, tilanne nyt

| Vaihe | Muutos | Jäljellä oleva este |
|---|---|---|
| 1. Herätys (some) | — | og-tiedot kertovat vanhaa viestiä (U3). Ei UTM-seurantaa. |
| 2. Kartoitus (etusivu) | Lupaus, hinta, kohdekuvaus ja Sami ovat nyt kunnossa | Ajankohta (U1) ja vastaväitteet (U2) puuttuvat. Tietosuojalinkki vie ristiriitaiseen selosteeseen. |
| 3. Lämmittely (listalla) | — | ⚠️ AVOIN: Buttondownin tervetuloviestin sisältö on yhä tuntematon. Kolmen viikon odotus on lyhyt, mutta tervetuloviestin pitäisi kertoa "kohteet tulevat arviolta marraskuun puolivälissä". |
| 4. Vertailu (kohdeviesti) | — | ⚠️ AVOIN: miten vuokrat, laskelma ja dokumentit esitetään viestissä. Mitä mittaria tuotolle käytetään (ks. #4)? |
| 5. Päätös | — | "Näin kauppa etenee ilman välittäjää" puuttuu edelleen. Tarve korostuu, koska kaupat tehdään sähköpostin kautta. |

### 2.6 CTA-suunnitelma, tilanne nyt

| Kitka | CTA | Tila |
|---|---|---|
| Matala | Tilaa Ketun kodit, etusivun hero + ankkurinapit `#liity` | ✅ Toimii. Ankkuri on olemassa. Hero-lomake on etusivun ainoa lomake. |
| Matala | Tilaa, sijoitussivu | ✅ |
| Matala | Tilaa, kohdesivut ja myyty kohde | ✖ Puuttuu |
| Matala | Toinen lomake etusivun loppuun | ✖ Tuotannossa nappi vie takaisin ylös. Localhostissa lomake on myös lopussa. |
| Keski | Esimerkkilaskelma | ✖ Ei aloitettu |
| Korkea | Ota yhteyttä | ✅ |

---

## Osio 3: Brändi-identiteetti
Ei muutosta. Tone of voice on tarkentunut tämän päivän työssä:
- kevyt, kuiva huumori minä-muodossa
- ei "kettu teki X" -lauseita
- ei kaupunkia otsikoissa
- huumori tekstissä, ei numeroissa

Tuotannon kortit ja otsikot noudattavat tätä hyvin. Poikkeukset ovat kielioppivirheet (U4, U5, U7).

## Osio 4: Kevyt sisältökonsepti (muutokset)

### 4.4 Pääviestit, tilanne nyt
1. **Uudet sijoitusasunnot myydään vain listalle.** ✅ Selkeä. Lisää ajankohta ("arviolta marraskuussa").
2. **Numerot auki.** ◐ Hinnat ovat nyt rehellisiä, mutta tuottomittari puuttuu ja lukuja on kolmea eri muotoa.
3. **Suoraan omistajalta.** ✅ "Myyn kohteet yleensä ilman välittäjää."
4. **Kettu on oikea ihminen.** ✅ Etusivulla.

## Osio 5: Tekniikka ja auditit

### Lighthouse (mobiili, etusivu, 24.10.)
Saavutettavuus 100, parhaat käytännöt 100, SEO 100, Agentic browsing 100. Yhtään auditointia ei hylätty.

Perusasiat ovat kunnossa:
- `lang="fi"`, canonical, robots
- otsikkohierarkia (yksi H1, sitten H2/H3)
- lomakkeen label ja aria-describedby
- TTFB noin 0,1 s (LiteSpeed)

Suorituskykyä (LCP/CLS) ei mitattu erikseen.

### SEO / GEO (tekninen)
- Schema: WebSite, WebPage, BreadcrumbList, Organization (The SEO Framework).
  💡 Lisää `Person` (Sami Keijonen, sameAs Instagram/YouTube/Sijoitusasuntovahti) Organizationin `founder`-kentäksi. Se tukee AI-hakujen "kuka myy" -vastauksia.
- `llms.txt` on olemassa, mikä on hyvä. Sisältö on vanhentunut (U9).
  💡 Päivitä se kuvaamaan "vain listalle" -mallia, seuraavaa erää (kaksiot, Lahden keskustan kupeessa, myyntihinnat alkaen 26 000 €, velaton alkaen 138 725 €) ja yhtä määriteltyä tuottolukua.
- Etusivulla ei ole FAQ-schemaa, ja sen voi jättää pois. UKK-sivun FAQ-schemaan tulee listakysymykset, kun localhostin UKK-osio viedään tuotantoon.
- ⚠️ AVOIN: Hakukysyntää ("sijoitusasunto Lahti", "valmiiksi vuokrattu sijoitusasunto") ei mitattu. `seo-audit` vaatii DataForSEO-yhteyden, jota tässä sessiossa ei ole kirjautuneena.

### Must have ennen 15.11.
1. **Tietosuojaseloste:** uutiskirje käyttötarkoituksena, Buttondown käsittelijänä, peruste (suostumus), siirrot EU:n ulkopuolelle, säilytysaika, peruminen. Poista lause "Emme luovuta…" ja korjaa yhteystieto, jossa viitataan olemattomaan lomakkeeseen.
2. **Mittaus:**
   - Palauta lomakkeisiin piilokenttä `tag`, mieluiten sivukohtaisena (`etusivu`, `sijoitusasunnot`, `tilaa`).
   - Lisää evästeetön analytiikka.
   - Käytä UTM-linkkejä Instagram-bion ja YouTube-kuvausten linkeissä.
   - Buttondown tallentaa myös tilaajan lähde-URL:n (referrer), jos se on käytössä. ⚠️ Tarkista Buttondownin asetuksista.
3. **Vie tuotantoon** localhostin "Hyvä tietää" -osio ja UKK:n "Ketun kodit -lista" (U1, U2).
4. **og:title / og:description** etusivulle (U3).
5. **Kirjoitusvirheet** U4, U5, U7.

### Jatkokehitys
Pysyvät edellisestä versiosta:
- tilauslaatikko kohdesivuille ja myydyn kohteen "Myöhästyitkö?" -CTA
- esimerkkilaskelma
- "Näin kauppa etenee" -osio
- tervetulosarja

## Osio 6: Avoimet asiat ja seuraavat askeleet

### 6.1 Puuttuvat tiedot (Samilta)
1. Listan nykyinen tilaajamäärä ja tavoite 15.11. mennessä.
2. Mihin "noin 5 %" perustuu (vuokratuotto velattomasta hinnasta?), ja onko `llms.txt`:n "5–6 %" vanha luku.
3. Buttondownin tervetuloviestin sisältö.
4. Poistettiinko tuotannon lomakkeesta `tag=avajaiset` tarkoituksella?

### 6.2 Päätökset
1. Mikä on tuoton pääluku ja sen määritelmä. Sama luku tulee joka paikkaan: etusivu, sijoitussivu, meta, `llms.txt` ja kohdeviesti.
2. Lisätäänkö kohdesivuille tilauslaatikko jo ennen 15.11.? Espoon kohteiden kävijät ovat valmiiksi kiinnostuneita sijoittajia.
3. "Avajaiset": selitetäänkö vai poistetaanko otsikosta?

### 6.4 Seuraavat askeleet
1. **Tällä viikolla:**
   - tietosuojaseloste
   - tagit ja analytiikka
   - localhostin muutokset tuotantoon
   - og-tiedot ja kirjoitusvirheet
2. **Ennen 15.11.:**
   - tervetuloviesti kertoo aikataulun
   - `llms.txt` päivitetty
   - yksi tuottoluku joka paikassa
3. **15.11. jälkeen:** mittaa tilaajamäärä, kohdeviestin avaukset ja klikit, ja montako kohdetta myytiin listalle. Kirjaa ostajien polku (mistä he tulivat listalle).
4. Valinnainen syvennys:
   - `design-audit` (etusivu ja sijoitussivu, rinnakkaisagentit)
   - `seo-audit`, kun DataForSEO on kytketty
   - `geo-audit` AI-näkyvyydestä

---

## QA-yhteenveto
- **Tarkistetut lähdemateriaalit:**
  - Tuotannon HTML 24.10. noin klo 21.30: etusivu, Myytävät sijoitusasunnot, Tilaa Ketun kodit, Tietosuojaseloste, UKK, 3 kohdesivua, Ota yhteyttä -lomake.
  - `robots.txt`, `sitemap.xml` ja `llms.txt`.
  - Lomakkeiden kentät (action, input, hidden).
  - Meta- ja og-tiedot, schema-tyypit, analytiikkaskriptit (ei löytynyt).
  - Lighthouse mobiili.
  - Edellinen strategiadokumentti.
  - Teeman ja pluginin koodi (tag-kentän haku).
- **Löydetyt ja korjatut ongelmat:**
  - Ensimmäinen tarkistus tulkitsi kohdesivujen lomakkeen tilauslomakkeeksi (väärä hakulauseke). Uudelleentarkistus näytti, että se on Contact Form 7, joten havainto 8 on yhä voimassa.
  - Etusivun "noin 5 %" ja kohdesivun "13 %" pidettiin eri mittareina, kuten edelliselläkin kerralla.
- **Avoimiin asioihin siirretyt:** tilaajamäärä, tuottoluvun perusta, tervetuloviesti, `tag`-kentän poiston syy, hakukysyntä, Buttondownin referrer-tallennus.

## QA-kerros
- Tarkistettu: strategia-tarkistuslistan seitsemän kohtaa. Edellisen version 12 havaintoa verrattiin yksitellen tuotannon HTML:ään 24.10.2026. Uudet havainnot (U1–U11) jäljitettiin suoraan sivujen lähdekoodiin tai meta-tietoihin. Lighthouse ajettiin mobiilina.
- Korjattu: kohdesivujen lomaketulkinta tarkistettiin uudelleen ja korjattiin. Tilaajamääriä tai konversiolukuja ei esitetty, koska dataa ei ole.
- Epävarmaa: (1) onko `tag`-kentän puuttuminen tarkoituksellista; (2) tietosuojaselosteen vaatimukset on syytä tarkistaa juridisesti, tässä niitä ei verifioitu; (3) LCP/CLS ja hakukysyntä jäivät mittaamatta; (4) localhostin muutokset (Hyvä tietää, UKK-osio, og-tiedot) eivät ole tuotannossa, joten niiden vaikutusta ei testattu tuotannossa.
- Vaikutusluokka: korkea — dokumentti sisältää hintoja ja väitteitä sivuston nykytilasta, ja tietosuojahavainnolla on juridista merkitystä.
