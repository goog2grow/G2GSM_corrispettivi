# Migrazioni

`schema.sql` (nella root del progetto) va eseguito una sola volta, su un
database vuoto, per creare lo schema iniziale.

Ogni modifica successiva allo schema va fatta con uno script incrementale
in questa cartella, numerato progressivamente, ad esempio:

```
migrations/001_aggiungi_colonna_x.sql
migrations/002_nuova_tabella_y.sql
```

Regole:

- mai modificare `schema.sql` dopo la messa in produzione;
- mai rieseguire `schema.sql` su un database che contiene gia' dati;
- ogni migrazione deve essere scritta per essere eseguita una sola volta
  e in modo additivo (evitare `DROP TABLE` / `DROP DATABASE` salvo
  rollback esplicitamente richiesti).
