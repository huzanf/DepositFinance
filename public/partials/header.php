<?php
// Which visual theme to render is picked in config.php ('ui.theme' => 'one' | 'zero'),
// not by the user in the browser — see config/config.example.php for details.
require __DIR__ . '/' . (current_theme() === 'zero' ? 'header_zero.php' : 'header_one.php');
