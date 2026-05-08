<?php
// Kullanım dışı. Artık şifreler düz metin saklanıyor; hash gerekmiyor.
fwrite(STDERR, "Bu araç artık kullanılmıyor. Şifreler düz metin saklanıyor.\n");
fwrite(STDERR, "Kullanıcı eklemek için admin paneli (/admin/teachers veya /teacher/students) ya da `php tools/install.php`.\n");
exit(1);
