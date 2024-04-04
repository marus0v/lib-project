<?php

namespace LibProject;

class Validator
{
    public function validate(string $url)
    {
        $errors = [];
        if ($url == '') {
            $errors['url'] = "Can't be blank";
        }
        return $errors;
    }
}