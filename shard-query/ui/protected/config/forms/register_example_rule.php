<?php

return array(
        'username'=>array(
                        array('username',4,5,'username invalid'),
                        array('maxlength',6,'This is too long'),
                        array('minlength',6)
                    ),
        'pwd'=>array('password',3,5),
        'email'=>array('email')
    );

?>