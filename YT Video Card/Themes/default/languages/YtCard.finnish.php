<?php
// Version: 2.1; YtCard

// Admin
$txt['ytcard_title'] = 'YouTube-videokortti';
$txt['ytcard_settings_desc'] = 'Päivittää nykyisen <strong>[youtube]</strong>-tagin. Muokkaimen YouTube-painike toimii kuten ennenkin; viesteissä näytetään nyt video sekä sen otsikko, kuvaus ja suosituimmat kommentit. Kuvaus ja kommentit haetaan YouTube Data API -rajapinnasta, joten niitä varten tarvitaan API-avain. Ilman avainta näytetään vain video ja sen otsikko. Jos ennen asennusta julkaistut viestit näyttävät edelleen samalta kuin ennen, tyhjennä välimuisti palvelimen asetuksista.';
$txt['ytcard_enabled'] = 'Ota YouTube-videokortit käyttöön';
$txt['ytcard_api_key'] = 'YouTube Data API v3 -avain';
$txt['ytcard_api_key_sub'] = 'Luo avain Google Cloud Consolessa (ota käyttöön &quot;YouTube Data API v3&quot;). Rajoita avaimen käyttö tähän rajapintaan sekä tämän palvelimen IP-osoitteeseen tai tämän keskustelualueen verkko-osoitteeseen.';
$txt['ytcard_show_description'] = 'Näytä videon kuvaus';
$txt['ytcard_show_comments'] = 'Näytä kommentit';
$txt['ytcard_show_avatars'] = 'Näytä kommentoijien profiilikuvat';
$txt['ytcard_show_avatars_sub'] = 'Lataa pienet kuvat Googlen palvelimilta kävijän selaimeen.';
$txt['ytcard_comment_count'] = 'Näytettävien kommenttien määrä';
$txt['ytcard_comment_count_sub'] = '1–100.';
$txt['ytcard_comment_order'] = 'Kommenttien järjestys';
$txt['ytcard_order_relevance'] = 'Suosituimmat ensin';
$txt['ytcard_order_time'] = 'Uusimmat ensin';
$txt['ytcard_cache_hours'] = 'Videotietojen välimuistin kesto';
$txt['ytcard_hours'] = 'tuntia';
$txt['ytcard_cache_hours_sub'] = 'Asetusten tallentaminen tyhjentää välimuistin. Pidempi välimuistin kesto kuluttaa vähemmän päivittäistä YouTube API -kiintiötä.';

// Cards
$txt['ytcard_loading'] = 'Ladataan videon tietoja…';
$txt['ytcard_watch_on_youtube'] = 'Katso YouTubessa';
$txt['ytcard_more_on_youtube'] = 'Katso kuvaus ja kommentit YouTubessa';
$txt['ytcard_show_more'] = 'Näytä lisää';
$txt['ytcard_show_less'] = 'Näytä vähemmän';
$txt['ytcard_comments'] = 'Kommentit';
$txt['ytcard_all_comments'] = 'Katso kaikki kommentit YouTubessa';
$txt['ytcard_no_comments'] = 'Ei vielä kommentteja.';
$txt['ytcard_comments_disabled'] = 'Tämän videon kommentointi on poistettu käytöstä.';
$txt['ytcard_comments_error'] = 'Kommentteja ei voitu ladata juuri nyt.';
$txt['ytcard_views'] = 'Näyttökertoja: %s';
$txt['ytcard_likes'] = 'Tykkäyksiä: %s';
$txt['ytcard_replies'] = 'Vastauksia: %s';
