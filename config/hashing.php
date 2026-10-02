<?php

return ['driver'=>env('HASH_DRIVER','bcrypt'),'bcrypt'=>['rounds'=>(int)env('BCRYPT_ROUNDS',12)],'argon'=>['memory'=>65536,'threads'=>4,'time'=>4]];
