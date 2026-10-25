# Learnings: Ketun koti Verkkosivustrategia

## Päivämäärä
24.10.2026

## Mikä toimi hyvin
- Pieni sivusto (10 sivua) oli mahdollista käydä läpi kokonaan HTML-tasolla. Lomakkeiden piilokentät (Buttondown-tagi) ja rikkinäinen footer-linkki löytyivät vain lähdekoodista, eivät tekstisisällöstä.
- Kohdesivujen numeroiden laskeminen auki (kassavirta) paljasti ristiriidan etusivun lupauksen kanssa. Numeropainotteisessa toimialassa tämä on arvokkaampaa kuin tekstin arviointi.
- Projektin oma koodi (tuottokaava pluginissa) auttoi erottamaan eri tuottomittarit virheistä.

## Mikä oli vaikeaa
- Skilli olettaa asiakasprojektin (ennakkokysymykset, positiointi). Tässä "asiakas" oli käyttäjä itse eikä pohjamateriaalia ollut, joten kaikki tavoitteet jäivät avoimiksi.
- Pyyntö oli rajattu (sähköpostilistan kasvatus), mutta skillin rakenne vaatii koko strategian. Rajaus tehtiin painottamalla osioita 1.3, 2.3, 2.4 ja 2.6 ja pitämällä brändi- ja tekniikkaosiot lyhyinä.
- Tech-context olettaa kala-stackin ja meomblocksin. Tämä projekti on block theme, joten 4.3 ja 5 piti sovittaa.

## Lähdemateriaalin laatu
Vain julkinen sivusto ja koodi. Ei analytiikkaa, ei tilaajadataa, ei ostajadataa.

## QA-havainnot
- Tuottoluvuissa oli kiusaus tulkita "5 %" vs "13 %" virheeksi. Ne ovat eri mittareita (tuotto velattomasta hinnasta vs. oman pääoman tuotto), joten havainto muotoiltiin "mittareita ei määritellä".
- Juridinen huomio (velattoman hinnan esittäminen markkinoinnissa) merkittiin tarkistettavaksi eikä faktaksi.

## Ehdotukset skillin parantamiseen
- Lisää "rajattu tavoite" -tila (esim. vain konversio- tai listastrategia), jossa brändi- ja tekniikkaosiot voi tiivistää.
- Lisää tarkistuskohta: "laske kohde- tai hintasivujen luvut auki ja vertaa etusivun lupauksiin" numeropainotteisille toimialoille.
- Lisää tech-contextiin maininta block theme -projekteista (ei meomblocks).
- Lisää tarkistus lomakkeiden piilokentistä ja lähdeseurannasta (tagit, UTM) osaksi CTA-osion mittaroinnin vaatimuksia.

---

# Learnings: Ketun koti, uusintatestaus

## Päivämäärä
24.10.2026 (illalla, tuotannon päivitysten jälkeen)

## Mikä toimi hyvin
- Kun edellisen version havainnot käytiin läpi taulukkona (korjattu / osittain / ennallaan), muutosten arviointi oli nopeaa ja konkreettista.
- Meta-, og- ja `llms.txt`-tiedot löysivät vanhentuneen viestin, jota sivun tekstistä ei näe.

## Mikä oli vaikeaa
- Localhost ja tuotanto erosivat. Osa suositelluista muutoksista oli tehty vain paikallisesti, joten testissä piti erottaa, mikä on tuotannossa ja mikä odottaa julkaisua.
- Kevyt regex-pohjainen HTML-luku tulkitsi kohdesivun lomakkeen väärin. Lomaketyyppi pitää tarkistaa luokan tai actionin perusteella, ei pelkän `<form`-tagin.

## Lähdemateriaalin laatu
Sama kuin edellisellä kerralla: vain julkinen sivusto ja koodi.

## QA-havainnot
- Tarkista aina, mikä meta-tagi on kyseessä (description vs og vs twitter), ennen kuin raportoit havainnon.

## Ehdotukset skillin parantamiseen
- Lisää "uusintatestaus"-tila: edellinen dokumentti syötteenä, tuloksena havaintojen tilataulukko ja uudet havainnot, ja muuttumattomat osiot viittauksella.
- Lisää tarkistuslistaan og-, twitter- ja `llms.txt`-tietojen vertailu sivun pääviestiin.
