Placer ici le certificat CA de votre serveur MySQL : `mysql-ca.pem`.

`Dbo` l'exige dès que `dbSsl` vaut `true` et que le driver est `mysql`.
En local (127.0.0.1 / localhost) on peut mettre `'dbSsl' => false` dans `conf.php`.
