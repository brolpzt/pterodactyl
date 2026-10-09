# Gcore DDoS Panel (referência)

Cópia do painel standalone original. A UI integrada ao Pterodactyl fica em:

- Admin: `/admin/gcore`
- Código: `app/Services/Gcore/`, `app/Http/Controllers/Admin/GcoreDdosController.php`
- Config: `GCORE_API_KEY` no `.env` do painel (`config/gcore.php`)

Não é necessário rodar o `start.sh` / PHP built-in server — use o menu **Admin → Gcore DDoS**.

Esta pasta permanece como referência do protótipo standalone (templates/src).
