<?php

class ilImsmExportConfig
{
    private ilSetting $settings;

    public function __construct(ilSetting $settings)
    {
        $this->settings = $settings;
        $this->settings->read();
    }

    public function getUseLogin() : bool
    {
        return (bool) $this->settings->get('use_login');
    }

    public function setUseLogin(bool $use_login)
    {
        $this->settings->set('use_login', (string) $use_login);
    }

    public function getUseFullname() : bool
    {
        return (bool) $this->settings->get('use_fullname');
    }

    public function setUseFullname(bool $use_fullname)
    {
        $this->settings->set('use_fullname', (string) $use_fullname);
    }


    public function getUseMatriculation() : bool
    {
        return (bool) $this->settings->get('use_matriculation');
    }

    public function setUseMatriculation(bool $use_matriculation)
    {
        $this->settings->set('use_matriculation', (string) $use_matriculation);
    }
}
