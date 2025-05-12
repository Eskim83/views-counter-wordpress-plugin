# Eskim Views Counter

**Prosty, lekki licznik odwiedzin stron w WordPressie z zapisem danych w bazie danych.**  
Zgodny z PHP 8.2 i najnowszymi standardami WordPressa.

---

## ✨ Funkcje

- Zlicza unikalne odwiedziny na podstawie IP
- Przypisuje wizyty do konkretnego posta lub strony
- Identyfikuje kraj użytkownika (na podstawie API ip-api.com)
- Rejestruje stronę odsyłającą (referer)
- Tworzy 3 tabele w bazie danych:
  - `eskim_views_counter_visitors`
  - `eskim_views_counter_countries`
  - `eskim_views_counter_referers`
- Posiada prosty panel statystyk w Kokpicie WordPressa
- Obsługuje PHP 8.2 i działa bez zewnętrznych zależności

---

## 🛠️ Wymagania

- PHP 8.2 lub nowszy  
- Dostęp do połączeń HTTP (`wp_remote_get`) dla API geolokalizacji

---

## 🚀 Instalacja

1. Skopiuj pliki wtyczki do katalogu `wp-content/plugins/eskim-views-counter/`
2. Aktywuj wtyczkę z poziomu Kokpitu WordPressa
3. Gotowe – zliczanie wizyt zaczyna się automatycznie w stopce strony (`wp_footer`)

---

## 🧼 Deinstalacja

Wtyczka zawiera plik `uninstall.php`, dzięki któremu po jej odinstalowaniu:
- automatycznie usuwane są wszystkie tabele z bazy danych

---

## 📊 Panel statystyk

Po aktywacji wtyczki w Kokpicie pojawia się pozycja **Licznik odwiedzin**, która pokazuje:
- Łączną liczbę unikalnych wizyt
- Listę odwiedzin według kraju
- Listę odwiedzin według strony odsyłającej

---

## 🧾 Historia wersji

### Wersja 1.2

- W pełni zgodna z PHP 8.2
- Zastąpienie `file_get_contents()` przez `wp_remote_get()`
- Dodano prosty panel admina (statystyki)
- Lepsze typowanie funkcji, refaktoryzacja kodu

### Wersja 1.1

- Refaktoryzacja całej wtyczki: zmiana nazw funkcji, struktura danych
- Nowy prefiks `eskim_views_counter_` dla lepszej spójności
- Obsługa IP, walidacja danych, przygotowanie pod modularność

### Wersja 1.0

- Pierwsza publiczna wersja (opis: [Artykuł na blogu](https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/))

---

## 💡 Plany na przyszłość

- Filtrowanie po dacie i zakresie
- Eksport CSV
- Dashboard z wykresami (Chart.js)
- Tryb headless (REST API endpoints)

---

## ✍️ Autor

Maciej Włodarczak – [eskim.pl](https://eskim.pl)  
[GitHub: Eskim83](https://github.com/Eskim83)

---

## ☕ Wesprzyj rozwój

Jeśli ta wtyczka Ci się przydała — możesz postawić mi kawę:  
👉 [https://www.buymeacoffee.com/eskim](https://www.buymeacoffee.com/eskim)

---

## 🔒 Licencja

GPL v3 or later – darmowa do użytku prywatnego i komercyjnego.
