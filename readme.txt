=== České služby pro WordPress ===
Donate link: http://www.separatista.net
Tags: Heureka.cz, Sklik.cz, WooCommerce, Ulozenka.cz, Srovname.cz, DPD, Zbozi.cz, Pricemania.cz
Requires at least: 4.0
Tested up to: 4.2.2
Stable tag: 0.4

Implementace různých českých služeb do WordPressu

== Description ==

Implementace různých českých služeb do WordPressu.

Plugin sice na několika webech bez problémů funguje, ale berte ho prosím stále jako testovací verzi.

Pro správnou funkčnost vyžaduje WooCommerce verzi 2.2.x.

Plugin zatím podporuje následující služby a pluginy:

* WooCommerce: Ověřeno zákazníky (Heureka.cz a Heureka.sk)
* WooCommerce: Měření konverzí (Heureka.cz a Heureka.sk)
* WooCommerce: Měření konverzí (Sklik.cz)
* WooCommerce: Měření konverzí (Srovname.cz)
* WooCommerce: Doprava (Uloženka.cz) - CZ i SK
* WooCommerce: Doprava (DPD ParcelShop) - CZ i SK
* WooCommerce: Možnost změny provedených objednávek v případě dobírky
* WooCommerce: XML feed (Heureka.cz a Heureka.sk)
* WooCommerce: XML feed (Zbozi.cz)
* WooCommerce: Omezení nabídky dopravy, pokud je dostupná zdarma
* WooCommerce: Zobrazování recenzí ze služby Ověřeno zákazníky pomocí shortcode (Heureka.cz a Heureka.sk)
* WooCommerce: XML feed (Pricemania.cz a Pricemania.sk)

== Oficiální podpora pluginu ==

Fórum podpory: http://www.separatista.net/forum

== Frequently Asked Questions ==

**Jak plugin správně nastavit?**

Aktivovat plugin a přejít do menu WooCommerce - Nastavení - záložka České služby.

== Changelog ==

= 0.4 =
* WooCommerce: Ověřeno zákazníky (Heureka.sk)
* WooCommerce: XML feed (Zbozi.cz)
* WooCommerce: Doprava (DPD ParcelShop)
* Základní implementace (není přímo napojeno na provozovatele)
* Možnost provozovat samostatně nebo jako dodatečné pobočky pro Uloženku
* Nastavení jednotné ceny za doručení a dobírku (CZ i SK)
* Automatická nabídka poboček podle zvolené země zákazníka (CZ i SK)
* Uloženka nově podporuje všechny výše uvedené funkce jako DPD ParcelShop
* Napojeno na API Uloženky (nastavení obchodu a volba poboček)
* WooCommerce: Zobrazování recenzí ze služby Ověřeno zákazníky pomocí shortcode (Heureka.cz a Heureka.sk)
* Aktualizace recenzí jednou denně
* Jednoduchý shortcode: [heureka-recenze-obchodu]
* WooCommerce: Měření konverzí (Heureka.sk)
* Automatická podpora slovenské verze podle nastavené lokalizace
* Optimalizace databázového dotazu pro generování XML feedů
* WooCommerce: XML feed (Pricemania.cz a Pricemania.sk)

= 0.3 =
* WooCommerce: XML feed (Heureka.cz)
* Možnost volitelné aktivace generovaného feedu
* Implemetace samostatné záložky s nastavením
* Možnost nastavení globální dodací doby pro všechny produkty
* Možnost zobrazovat EAN kód pokud ho zadávate do pole SKU
* Skryté produkty nejsou součástí feedu
* Generován základní strom použitých kategorií
* Pro snadné generování feedu použita PHP knihovna XMLWriter
* WooCommerce: Omezení nabídky dopravy, pokud je dostupná zdarma

= 0.2 =
* WooCommerce: Měření konverzí (Srovname.cz)
* WooCommerce: Možnost změny provedených objednávek v případě dobírky
* Oprava zobrazení Uloženky ve WooCommerce 2.3.x
* Plugin vyžaduje WooCommerce verzi 2.2.x
* Použití funkce wc_add_notice()
* Drobné čistky a úpravy

= 0.1 =
* WooCommerce: Ověřeno zákazníky (Heureka.cz)
* WooCommerce: Měření konverzí (Heureka.cz)
* WooCommerce: Měření konverzí (Sklik.cz)
* WooCommerce: Doprava (Uloženka.cz)

== Screenshots ==

Screenshoty budou doplněny.

== Installation ==

Instalovat plugin, aktivovat a přejít do menu WooCommerce - Nastavení - záložka České služby.
