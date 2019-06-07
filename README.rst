*****************
OUYA store mirror
*****************

Download OUYA games before they are gone.

The discover download is broken.
Only the direct game download works, which you need an OUYA game uuid list for.

Usage
=====
1. copy `config.php.dist` to `config.php` and add your ouya login data
2. download a single game::

     $ php mirror.php com.explusalpha.Snes9xPlus
3. Download all games::
     
     $ cat game-uuids-sorted | xargs -L1 php mirror.php
