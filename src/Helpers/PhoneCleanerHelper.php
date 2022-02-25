<?php

namespace Ma\AuthOtpApi\Helpers;

class PhoneCleanerHelper
{
    private $phoneNumber;

    private $countryCode;

    public function __construct($phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function clean()
    {
        $this->findCountry();

        switch ($this->countryCode) {
            case 'IQ':
                return $this->cleanIQPhoneNumber();
                break;
            default:
                return $this->phoneNumber;
                break;
        }
    }

    /**
     * Find country code from phone number string
     *
     * @return void
     */
    private function findCountry()
    {
        foreach ($this->countryList() as $country => $code) {
            if (preg_match('#^(' . implode("|", $code) . ')(.*)$#i', $this->phoneNumber)) {
                $this->countryCode = $country;
            }
        }
    }


    /**
     * Array list of phone number country codes
     *
     * @return array
     */

    private function countryList(): array
    {
        return [
            // we put the / before the +964 for the preg_match function
            'IQ' => ['\+964', '00964', '964','07','7']
        ];
    }

    /**
     * clean phone number make it with the format of "+964"
     *
     * @return string|bool
     * */
    private function cleanIQPhoneNumber()
    {
        // remove all whitespaces
        $phone = preg_replace('/\s+/', '', $this->phoneNumber);

        // validate the number is only contains numbers
        if (!preg_match_all('!\d{10,15}+!', $phone)) {
            return false;
        }

        // switch the cases of the number
        switch ($phone):
            case (strlen($phone) === 13 && substr($phone, 0, 4) === "9647"):
                return "+" . $phone;
        break;
        case (strlen($phone) === 14 && substr($phone, 0, 5) === "+9647"):
                return $phone;
        break;
        case (strlen($phone) === 15 && substr($phone, 0, 6) === "009647"):
                return "+" . substr($phone, 2);
        break;
            case (strlen($phone) === 11 && substr($phone, 0, 1) === "0"):
                return "+964" . substr($phone, 1);
                break;
            case (strlen($phone)=== 12 && substr($phone,0,2)==="07");
                return "+964" . substr($phone,2);
                break;
            default:
                return false;
        break;
        endswitch;
    }

}
