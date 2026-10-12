<?php
// La racine du projet n'est jamais servie : seul public/ est la racine web.
http_response_code(403);
echo "403 Forbidden";
exit;
