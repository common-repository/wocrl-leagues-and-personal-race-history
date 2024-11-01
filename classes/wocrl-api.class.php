<?php
/**
 * Created by PhpStorm.
 * User: Steph
 * Date: 06/01/2020
 * Time: 16:38
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ServerException;

global $WOCRL_API;

/*
 * Main class for WOCRL methods
 *
 * */
class WOCRL_PLUGIN_API {

    private $uri = 'https://wocrl.org/';
    private $api_key = false;

    function __construct()
    {

        session_start();

        // add shortcodes
        add_shortcode('trophy_hunters_league', array($this, 'get_th_league_table'));
        add_shortcode('fun_runners_league', array($this, 'get_fr_league_table'));
        add_shortcode('community_league', array($this, 'get_community_league_table'));
        add_shortcode('race_directors_league', array($this, 'get_race_directors_league_table'));
        add_shortcode('personal_trophy_hunters_history', array($this, 'get_th_race_history_table'));
        add_shortcode('personal_fun_runners_history', array($this, 'get_fr_race_history_table'));
        add_shortcode('wocrl_championship_league', array($this, 'get_wocrl_championship_data_table'));
        add_shortcode('personal_wocrl_championship_data', array($this, 'get_personal_th_race_data_table'));
        add_shortcode('personal_fun_runners_data', array($this, 'get_personal_fr_race_data_table'));

        // get api get from plugin settings
        $wocrl_api_settings = get_option('wocrl_api_settings');
        $this->api_key = $wocrl_api_settings['wocrl_api_key'];

        try {
            $this->client = new Client( [
                'base_uri' => $this->uri . '/api/',// Base URI is used with relative requests
            ] );

        }catch(Exception $e){
            aub_add_to_messages('Connectivity issue, you may not see all content.','error');
        }

    }

    /*
     * Parse the guzzle response into array format
     * */
    public function parse_guzzle_response($response, $convert_to_array=false){
        $body = $response->getBody();

        //echo $body;
        if($convert_to_array) {
            $x = json_decode(json_decode($body->getContents()), $convert_to_array);
        }else{
            $x = json_decode(json_decode($body->getContents()));
        }
        return $x;
    }

    /*
     * Check if the user can access certain content/functions
     */
    public function can_access(){
        $error = false;
        if(is_user_logged_in()){// is logged in
            if ($this->validate_wocrl_email()) {
                return true;
            }else{
                $error = new WP_Error('401','You must be a valid WOCRL member to do this');
            }
        }else{
            $error = new WP_Error('401','You must be logged in to do this');
        }

        return $error;
    }

    // <editor-fold defaultstate="collapsed" desc="Calculations">

    /*
     * Calculate average for league table outputs
     */
    public function calculate_average($array){
        $average = false;
        if($array){
            $average = array_sum($array) / count($array);
        }
        return $average;
    }

    /*
     *  Calculate overall average for league table outputs
     */
    public function calculate_overall_average($array, $field){
        $overall_average = false;

        if($array && $field){
            $main_array = array();
            foreach($array as $value){
                if($field == 'average_ocr_speed_kms' || $field == 'average_ocr_speed_miles'){
                    $main_array[] =  $this->calculate_average(array_map('strtotime', array_column($value, $field)));
                }else{
                    $main_array[] =  $this->calculate_average(array_column($value, $field));
                }
            }

            $overall_average = $this->calculate_average($main_array);
        }

        return $overall_average;
    }

    /*
     * Calculate trophy hunter running points
     */
    public function calculate_trophy_hunter_running_points($overall=0, $age=0, $gender=0){
        $trophy_hunter_running_points = ($overall + $age + $gender);

        return $trophy_hunter_running_points;
    }

    /*
     * Calculate position points for overall, age and gender
     */
    public function calculate_position_points($position=false){
        $points = 0;
        if($position <= 30) {
            // create array where key is position e.g. 30 points = 1st position (that's why we are creating array in reverse order)
            $possible_points = range(30, 1);
            // need to add one because array starts at 0
            $points = array_search($position, $possible_points) + 1;
        }
        return $points;
    }

    /*
     * Calculate overall position points
     */
    public function calculate_overall_position_points($race_stat_id, $event_id){
        $overall_position_points = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){

                $position = array_search($race_stat_id, $all_event_race_stats) + 1;
                if($position){
                    $overall_position_points = $this->calculate_position_points($position);
                }
            }
        }
        return $overall_position_points;
    }

    /*
    * Calculate age group position points
    */
    public function calculate_age_group_position_points($race_stat_id, $event_id, $age_group){
        $age_group_position_points = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id,
                'age_group' => $age_group
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){
                $position = array_search($race_stat_id, $all_event_race_stats) + 1;
                if($position){
                    $age_group_position_points = $this->calculate_position_points($position);
                }
            }
        }
        return $age_group_position_points;
    }

    /*
    * Calculate gender position points
    */
    public function calculate_gender_position_points($race_stat_id, $event_id, $gender){
        $gender_position_points = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id,
                'user_gender' => $gender
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){
                $position = array_search($race_stat_id, $all_event_race_stats) + 1;
                if($position){
                    $gender_position_points = $this->calculate_position_points($position);
                }
            }
        }
        return $gender_position_points;
    }

    /*
     * Calculate overall position
     */
    public function calculate_overall_position($race_stat_id, $event_id){
        $overall_position = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){
                $overall_position = array_search($race_stat_id, $all_event_race_stats) + 1;
            }
        }
        return $overall_position;
    }

    /*
    * Calculate age group position
    */
    public function calculate_age_group_position($race_stat_id, $event_id, $age_group){
        $age_group_position = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id,
                'age_group' => $age_group
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){
                $age_group_position = array_search($race_stat_id, $all_event_race_stats) + 1;
            }
        }
        return $age_group_position;
    }

    /*
    * Calculate gender position points
    */
    public function calculate_gender_position($race_stat_id, $event_id, $gender){
        $gender_position = false;

        if($race_stat_id && $event_id){
            // get everyone from this race
            $vars = array(
                'event_id' => $event_id,
                'user_gender' => $gender
            );

            $all_event_race_stats = $this->get_leader_board($vars);

            // order them by hero rating and overall time
            array_multisort(
                array_column($all_event_race_stats, 'hero_rating'), SORT_DESC,
                array_column($all_event_race_stats, 'overall_time'), SORT_ASC,
                $all_event_race_stats);

            $all_event_race_stats = array_column($all_event_race_stats, 'race_stat_id');

            if(in_array($race_stat_id, $all_event_race_stats)){
                $gender_position = array_search($race_stat_id, $all_event_race_stats) + 1;
            }
        }
        return $gender_position;
    }

    /*
     * Calculate average event course rating
     */
    public function calculate_average_event_course_rating($event_id){
        $average_event_course_rating = 0;

        if($event_id){

            $args = array(
                'event_id' => $event_id
            );

            $non_filtered_race_stats = $this->get_leader_board($args);
            $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

            if($race_stats){
                $average_event_course_rating = $this->calculate_average(array_column($race_stats, 'course_rating'));
            }
        }

        return $average_event_course_rating;
    }

    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Get Functions">

    /*
     * Get all possible distance categories
     */
    public function get_distance_categories(){
        return array(
            0 => array(
                'name' => 'Short - Up to 4.99KMs',
                'min' => 0,
                'max' => 4.99
            ),
            1 => array(
                'name' => 'Standard - 5KMs > 9.99KMs',
                'min' => 5,
                'max' => 9.99
            ),
            2 => array(
                'name' => 'Mid - 10KMs > 19.99KMs',
                'min' => 10,
                'max' => 19.99
            ),
            3 => array(
                'name' => 'Long - 20 KMs > 44.99KMs',
                'min' => 20,
                'max' => 44.99
            ),
            4 => array(
                'name' => 'Ultra - 45 KMs +',
                'min' => 45,
                'max' => 9999
            ),
        );
    }

    /*
     * Get single distance_category
     */
    public function get_distance_category($distance_category_id){
        $distance_category = false;

        if(is_numeric($distance_category_id)){
            $all_distance_categories = $this->get_distance_categories();
            $distance_category = $all_distance_categories[$distance_category_id];
        }

        return $distance_category;
    }

    /*
     * Get all possible age groups
     */
    public function get_age_groups(){
        return array(
            0 => array(
                'name' => 'Junior (Up to 15)',
                'min' => 0,
                'max' => 15
            ),
            1 => array(
                'name' => '16 - 17',
                'min' => 16,
                'max' => 17
            ),
            2 => array(
                'name' => '18 - 24',
                'min' => 18,
                'max' => 24
            ),
            3 => array(
                'name' => '25 - 29',
                'min' => 25,
                'max' => 29
            ),
            4 => array(
                'name' => '30 - 34',
                'min' => 30,
                'max' => 34
            ),
            5 => array(
                'name' => '35 - 39',
                'min' => 35,
                'max' => 39
            ),
            6 => array(
                'name' => '40 - 44',
                'min' => 40,
                'max' => 44
            ),
            61 => array(
                'name' => '45 - 49',
                'min' => 40,
                'max' => 44
            ),
            7 => array(
                'name' => '50 - 54',
                'min' => 50,
                'max' => 54
            ),
            8 => array(
                'name' => '55 - 59',
                'min' => 55,
                'max' => 59
            ),
            9 => array(
                'name' => '60+',
                'min' => 60,
                'max' => 9999
            ),
        );
    }

    /*
    * Get single age group
    */
    public function get_age_group($age_group_id){
        $age_group = false;
        if($age_group_id){
            $age_groups = $this->get_age_groups();
            $age_group = $age_groups[$age_group_id]['name'];
        }
        return $age_group;
    }

    /*
     * Get all leagues
     */
    public function get_leagues(){
        return array(
            0 => 'Trophy Hunter',
            1 => 'Fun Runner',
            2 => 'Race Director',
            3 => 'Community'
        );
    }

    /*
     * Get single league
     */
    public function get_league($race_league_id){
        $race_league = false;

        if(is_numeric($race_league_id)){
            $all_race_leagues = $this->get_leagues();
            $race_league = $all_race_leagues[$race_league_id];
        }

        return $race_league;
    }

    /*
     * Get number of races categories
     */
    public function get_number_of_races_categories(){
        return array(
            0 => array(
                'label' => '0 - 5',
                'min' => 0,
                'max' => 5
            ),
            1 => array(
                'label' => '6 - 10',
                'min' => 6,
                'max' => 10
            ),
            2 => array(
                'label' => '11 - 15',
                'min' => 11,
                'max' => 15
            ),
            3 => array(
                'label' => '16 - 20',
                'min' => 16,
                'max' => 20
            ),
            4 => array(
                'label' => '21 - 25',
                'min' => 21,
                'max' => 25
            ),
            5 => array(
                'label' => '26 - 30',
                'min' => 26,
                'max' => 30
            ),
            6 => array(
                'label' => '31 - 35',
                'min' => 31,
                'max' => 35
            ),
            7 => array(
                'label' => '36 - 40',
                'min' => 36,
                'max' => 40
            ),
            8 => array(
                'label' => '41 - 45',
                'min' => 41,
                'max' => 45
            ),
            9 => array(
                'label' => '46 - 50',
                'min' => 46,
                'max' => 50
            ),
            10 => array(
                'label' => '51 - 55',
                'min' => 51,
                'max' => 55
            ),
            11 => array(
                'label' => '56 - 60',
                'min' => 56,
                'max' => 60
            ),
            12 => array(
                'label' => '61 - 65',
                'min' => 61,
                'max' => 65
            ),
            13 => array(
                'label' => '66 - 70',
                'min' => 66,
                'max' => 70
            ),
            14 => array(
                'label' => '71 - 75',
                'min' => 71,
                'max' => 75
            ),
            15 => array(
                'label' => '76 - 80',
                'min' => 76,
                'max' => 80
            ),
            16 => array(
                'label' => '81 - 85',
                'min' => 81,
                'max' => 85
            ),
            17 => array(
                'label' => '86 - 90',
                'min' => 86,
                'max' => 90
            ),
            18 => array(
                'label' => '91 - 95',
                'min' => 91,
                'max' => 95
            ),
            19 => array(
                'label' => '96 - 100',
                'min' => 96,
                'max' => 100
            ),
            20 => array(
                'label' => '101 - 110',
                'min' => 101,
                'max' => 110
            ),
            21 => array(
                'label' => '111 - 120',
                'min' => 111,
                'max' => 120
            ),
            22 => array(
                'label' => '121 - 130',
                'min' => 121,
                'max' => 130
            ),
            23 => array(
                'label' => '131 - 140',
                'min' => 131,
                'max' => 140
            ),
            24 => array(
                'label' => '141 - 150',
                'min' => 141,
                'max' => 150
            ),
            25 => array(
                'label' => '151 - 160',
                'min' => 151,
                'max' => 160
            ),
            26 => array(
                'label' => '161 - 170',
                'min' => 161,
                'max' => 170
            ),
            27 => array(
                'label' => '171 - 180',
                'min' => 171,
                'max' => 180
            ),
            28 => array(
                'label' => '181 - 190',
                'min' => 181,
                'max' => 190
            ),
            29 => array(
                'label' => '191 - 200',
                'min' => 191,
                'max' => 200
            ),
            30 => array(
                'label' => '201 - 210',
                'min' => 201,
                'max' => 210
            ),
            31=> array(
                'label' => '211 - 220',
                'min' => 211,
                'max' => 220
            ),
            32 => array(
                'label' => '221 - 230',
                'min' => 221,
                'max' => 230
            ),
            33 => array(
                'label' => '231 - 240',
                'min' => 231,
                'max' => 240
            ),
            34 => array(
                'label' => '241 - 250',
                'min' => 241,
                'max' => 250
            ),
            35 => array(
                'label' => '251 - 260',
                'min' => 251,
                'max' => 260
            ),
            36 => array(
                'label' => '261 - 270',
                'min' => 261,
                'max' => 270
            ),
            37 => array(
                'label' => '271 - 280',
                'min' => 271,
                'max' => 280
            ),
            38 => array(
                'label' => '281 - 290',
                'min' => 281,
                'max' => 290
            ),
            39 => array(
                'label' => '291 - 300',
                'min' => 291,
                'max' => 300
            ),
            40 => array(
                'label' => '301 - 310',
                'min' => 301,
                'max' => 310
            ),
            41 => array(
                'label' => '311 - 320',
                'min' => 311,
                'max' => 320
            ),
            42 => array(
                'label' => '321 - 330',
                'min' => 321,
                'max' => 330
            ),
            43 => array(
                'label' => '331 - 340',
                'min' => 331,
                'max' => 340
            ),
            44 => array(
                'label' => '341 - 350',
                'min' => 341,
                'max' => 350
            ),
            45 => array(
                'label' => '351 - 360',
                'min' => 351,
                'max' => 360
            ),
            46 => array(
                'label' => '361 - 370',
                'min' => 361,
                'max' => 370
            ),
            47 => array(
                'label' => '371 - 380',
                'min' => 371,
                'max' => 380
            ),
            48 => array(
                'label' => '381 - 390',
                'min' => 381,
                'max' => 390
            ),
            49 => array(
                'label' => '391 - 400',
                'min' => 391,
                'max' => 400
            ),
            50 => array(
                'label' => '401 - 410',
                'min' => 401,
                'max' => 410
            ),
            51 => array(
                'label' => '411 - 420',
                'min' => 411,
                'max' => 420
            ),
            52 => array(
                'label' => '421 - 430',
                'min' => 421,
                'max' => 430
            ),
            53 => array(
                'label' => '431 - 440',
                'min' => 431,
                'max' => 440
            ),
            54 => array(
                'label' => '441 - 450',
                'min' => 441,
                'max' => 450
            ),
            55 => array(
                'label' => '451 - 460',
                'min' => 451,
                'max' => 460
            ),
            56 => array(
                'label' => '461 - 470',
                'min' => 461,
                'max' => 470
            ),
            57 => array(
                'label' => '471 - 480',
                'min' => 471,
                'max' => 480
            ),
            58 => array(
                'label' => '481 - 490',
                'min' => 481,
                'max' => 490
            ),
            59 => array(
                'label' => '491 - 500',
                'min' => 491,
                'max' => 500
            ),
            60 => array(
                'label' => '501 - 510',
                'min' => 501,
                'max' => 510
            ),
            61 => array(
                'label' => '511 - 520',
                'min' => 511,
                'max' => 520
            ),
            62 => array(
                'label' => '521 - 530',
                'min' => 521,
                'max' => 530
            ),
            63 => array(
                'label' => '531 - 540',
                'min' => 531,
                'max' => 540
            ),
            64 => array(
                'label' => '541 - 550',
                'min' => 541,
                'max' => 550
            ),
            65 => array(
                'label' => '551 - 560',
                'min' => 551,
                'max' => 560
            ),
            66 => array(
                'label' => '561 - 570',
                'min' => 561,
                'max' => 570
            ),
            67 => array(
                'label' => '571 - 580',
                'min' => 571,
                'max' => 580
            ),
            68 => array(
                'label' => '581 - 590',
                'min' => 581,
                'max' => 590
            ),
            69 => array(
                'label' => '591 - 600',
                'min' => 591,
                'max' => 600
            ),
            70 => array(
                'label' => '601 - 610',
                'min' => 601,
                'max' => 610
            ),
            71 => array(
                'label' => '611 - 620',
                'min' => 611,
                'max' => 620
            ),
            72 => array(
                'label' => '621 - 630',
                'min' => 621,
                'max' => 630
            ),
            73 => array(
                'label' => '631 - 640',
                'min' => 631,
                'max' => 640
            ),
            74 => array(
                'label' => '641 - 650',
                'min' => 641,
                'max' => 650
            ),
            75 => array(
                'label' => '651 - 660',
                'min' => 651,
                'max' => 660
            ),
            76 => array(
                'label' => '661 - 670',
                'min' => 661,
                'max' => 670
            ),
            77 => array(
                'label' => '671 - 680',
                'min' => 671,
                'max' => 680
            ),
            78 => array(
                'label' => '681 - 690',
                'min' => 681,
                'max' => 690
            ),
            79 => array(
                'label' => '691 - 700',
                'min' => 691,
                'max' => 700
            ),
            80 => array(
                'label' => '701 - 710',
                'min' => 701,
                'max' => 710
            ),
            81 => array(
                'label' => '711 - 720',
                'min' => 711,
                'max' => 720
            ),
            82 => array(
                'label' => '721 - 730',
                'min' => 721,
                'max' => 730
            ),
            83 => array(
                'label' => '731 - 740',
                'min' => 731,
                'max' => 740
            ),
            84 => array(
                'label' => '741 - 750',
                'min' => 741,
                'max' => 750
            ),
            85 => array(
                'label' => '751 - 760',
                'min' => 751,
                'max' => 760
            ),
            86 => array(
                'label' => '761 - 770',
                'min' => 761,
                'max' => 770
            ),
            87 => array(
                'label' => '771 - 780',
                'min' => 771,
                'max' => 780
            ),
            88 => array(
                'label' => '781 - 790',
                'min' => 781,
                'max' => 790
            ),
            89 => array(
                'label' => '791 - 800',
                'min' => 791,
                'max' => 800
            ),
            90 => array(
                'label' => '801 - 810',
                'min' => 801,
                'max' => 810
            ),
            91 => array(
                'label' => '811 - 820',
                'min' => 811,
                'max' => 820
            ),
            92 => array(
                'label' => '821 - 830',
                'min' => 821,
                'max' => 830
            ),
            93 => array(
                'label' => '831 - 840',
                'min' => 831,
                'max' => 840
            ),
            94 => array(
                'label' => '841 - 850',
                'min' => 841,
                'max' => 850
            ),
            95 => array(
                'label' => '851 - 860',
                'min' => 851,
                'max' => 860
            ),
            96 => array(
                'label' => '861 - 870',
                'min' => 861,
                'max' => 870
            ),
            97 => array(
                'label' => '871 - 880',
                'min' => 871,
                'max' => 880
            ),
            98 => array(
                'label' => '881 - 890',
                'min' => 881,
                'max' => 890
            ),
            99 => array(
                'label' => '891 - 900',
                'min' => 891,
                'max' => 900
            ),
            100 => array(
                'label' => '901 - 910',
                'min' => 901,
                'max' => 910
            ),
            101 => array(
                'label' => '911 - 920',
                'min' => 911,
                'max' => 920
            ),
            102 => array(
                'label' => '921 - 930',
                'min' => 921,
                'max' => 930
            ),
            103 => array(
                'label' => '931 - 940',
                'min' => 931,
                'max' => 940
            ),
            104 => array(
                'label' => '941 - 950',
                'min' => 941,
                'max' => 950
            ),
            105 => array(
                'label' => '951 - 960',
                'min' => 951,
                'max' => 960
            ),
            106 => array(
                'label' => '961 - 970',
                'min' => 961,
                'max' => 970
            ),
            107 => array(
                'label' => '971 - 980',
                'min' => 971,
                'max' => 980
            ),
            108 => array(
                'label' => '981 - 990',
                'min' => 981,
                'max' => 990
            ),
            109 => array(
                'label' => '991 - 1000',
                'min' => 991,
                'max' => 1000
            ),
            110 => array(
                'label' => '1001 - 1050',
                'min' => 1001,
                'max' => 1050
            ),
            111 => array(
                'label' => '1051 - 1100',
                'min' => 1051,
                'max' => 1100
            ),
            112 => array(
                'label' => '1101 - 1150',
                'min' => 1101,
                'max' => 1150
            ),
            113 => array(
                'label' => '1151 - 1200',
                'min' => 1151,
                'max' => 1200
            ),
            114 => array(
                'label' => '1201 - 1250',
                'min' => 1201,
                'max' => 1250
            ),
            115 => array(
                'label' => '1251 - 1300',
                'min' => 1251,
                'max' => 1300
            ),
            116 => array(
                'label' => '1301 - 1350',
                'min' => 1301,
                'max' => 1350
            ),
            117 => array(
                'label' => '1351 - 1400',
                'min' => 1351,
                'max' => 1400
            ),
            118 => array(
                'label' => '1401 - 1450',
                'min' => 1401,
                'max' => 1450
            ),
            119 => array(
                'label' => '1451 - 1500',
                'min' => 1451,
                'max' => 1500
            ),
            120 => array(
                'label' => '1501 - 1550',
                'min' => 1501,
                'max' => 1550
            ),
            121 => array(
                'label' => '1551 - 1600',
                'min' => 1551,
                'max' => 1600
            ),
            122 => array(
                'label' => '1601 - 1650',
                'min' => 1601,
                'max' => 1650
            ),
            123 => array(
                'label' => '1651 - 1700',
                'min' => 1651,
                'max' => 1700
            ),
            124 => array(
                'label' => '1701 - 1750',
                'min' => 1701,
                'max' => 1750
            ),
            125 => array(
                'label' => '1751 - 1800',
                'min' => 1751,
                'max' => 1800
            ),
            126 => array(
                'label' => '1801 - 1850',
                'min' => 1801,
                'max' => 1850
            ),
            127 => array(
                'label' => '1851 - 1900',
                'min' => 1851,
                'max' => 1900
            ),
            128 => array(
                'label' => '1901 - 1950',
                'min' => 1901,
                'max' => 1950
            ),
            129 => array(
                'label' => '1951 - 2000',
                'min' => 1951,
                'max' => 2000
            ),
            130 => array(
                'label' => '2001 - 2050',
                'min' => 2001,
                'max' => 2050
            ),
            131 => array(
                'label' => '2051 - 2100',
                'min' => 2051,
                'max' => 2100
            ),
            132 => array(
                'label' => '2101 - 2150',
                'min' => 2101,
                'max' => 2150
            ),
            133 => array(
                'label' => '2151 - 2200',
                'min' => 2151,
                'max' => 2200
            ),
            134 => array(
                'label' => '2201 - 2250',
                'min' => 2201,
                'max' => 2250
            ),
            135 => array(
                'label' => '2251 - 2300',
                'min' => 2251,
                'max' => 2300
            ),
            136 => array(
                'label' => '2301 - 2350',
                'min' => 2301,
                'max' => 2350
            ),
            137 => array(
                'label' => '2351 - 2400',
                'min' => 2351,
                'max' => 2400
            ),
            138 => array(
                'label' => '2401 - 2450',
                'min' => 2401,
                'max' => 2450
            ),
            139 => array(
                'label' => '2451 - 2500',
                'min' => 2451,
                'max' => 2500
            ),
            140 => array(
                'label' => '2501 - 2550',
                'min' => 2501,
                'max' => 2550
            ),
            141 => array(
                'label' => '2551 - 2600',
                'min' => 2551,
                'max' => 2600
            ),
            142 => array(
                'label' => '2601 - 2650',
                'min' => 2601,
                'max' => 2650
            ),
            143 => array(
                'label' => '2651 - 2700',
                'min' => 2651,
                'max' => 2700
            ),
            144 => array(
                'label' => '2701 - 2750',
                'min' => 2701,
                'max' => 2750
            ),
            145 => array(
                'label' => '2751 - 2800',
                'min' => 2751,
                'max' => 2800
            ),
            146 => array(
                'label' => '2801 - 2850',
                'min' => 2801,
                'max' => 2850
            ),
            147 => array(
                'label' => '2851 - 2900',
                'min' => 2851,
                'max' => 2900
            ),
            148 => array(
                'label' => '2901 - 2950',
                'min' => 2901,
                'max' => 2950
            ),
            149 => array(
                'label' => '2951 - 3000',
                'min' => 2951,
                'max' => 3000
            ),
            150 => array(
                'label' => '3001 - 3050',
                'min' => 3001,
                'max' => 3050
            ),
            151 => array(
                'label' => '3051 - 3100',
                'min' => 3051,
                'max' => 3100
            ),
            152 => array(
                'label' => '3101 - 3150',
                'min' => 3101,
                'max' => 3150
            ),
            153 => array(
                'label' => '3151 - 3200',
                'min' => 3151,
                'max' => 3200
            ),
            154 => array(
                'label' => '3201 - 3250',
                'min' => 3201,
                'max' => 3250
            ),
            155 => array(
                'label' => '3251 - 3300',
                'min' => 3251,
                'max' => 3300
            ),
            156 => array(
                'label' => '3301 - 3350',
                'min' => 3301,
                'max' => 3350
            ),
            157 => array(
                'label' => '3351 - 3400',
                'min' => 3351,
                'max' => 3400
            ),
            158 => array(
                'label' => '3401 - 3450',
                'min' => 3401,
                'max' => 3450
            ),
            159 => array(
                'label' => '3451 - 3500',
                'min' => 3451,
                'max' => 3500
            ),
            160 => array(
                'label' => '3501 - 3550',
                'min' => 3501,
                'max' => 3550
            ),
            161 => array(
                'label' => '3551 - 3600',
                'min' => 3551,
                'max' => 3600
            ),
            162 => array(
                'label' => '3601 - 3650',
                'min' => 1001,
                'max' => 1050
            ),
            163 => array(
                'label' => '3651 - 3700',
                'min' => 3651,
                'max' => 3700
            ),
            164 => array(
                'label' => '3701 -3750',
                'min' => 3701,
                'max' => 3750
            ),
            165 => array(
                'label' => '3751 - 3800',
                'min' => 3751,
                'max' => 3800
            ),
            166 => array(
                'label' => '3801 - 3850',
                'min' => 3801,
                'max' => 3850
            ),
            167 => array(
                'label' => '3851 - 3900',
                'min' => 3851,
                'max' => 3900
            ),
            168 => array(
                'label' => '3901 - 3950',
                'min' => 3901,
                'max' => 3950
            ),
            169 => array(
                'label' => '3951 - 4000',
                'min' => 3951,
                'max' => 4000
            ),
            170 => array(
                'label' => '4001 - 4050',
                'min' => 4001,
                'max' => 4050
            ),
            171 => array(
                'label' => '4051 - 4100',
                'min' => 4051,
                'max' => 4100
            ),
            172 => array(
                'label' => '4101 - 4150',
                'min' => 4101,
                'max' => 4150
            ),
            173 => array(
                'label' => '4151 - 4200',
                'min' => 4151,
                'max' => 4200
            ),
            174 => array(
                'label' => '4201 - 4250',
                'min' => 4201,
                'max' => 4250
            ),
            175 => array(
                'label' => '4251 - 4300',
                'min' => 4251,
                'max' => 4300
            ),
            176 => array(
                'label' => '4301 - 4350',
                'min' => 4301,
                'max' => 4350
            ),
            177 => array(
                'label' => '4351 - 4400',
                'min' => 4351,
                'max' => 4400
            ),
            178 => array(
                'label' => '4401 - 4450',
                'min' => 4401,
                'max' => 4450
            ),
            179 => array(
                'label' => '4451 - 4500',
                'min' => 4451,
                'max' => 4500
            ),
            180 => array(
                'label' => '4501 - 4550',
                'min' => 4501,
                'max' => 4550
            ),
            181 => array(
                'label' => '4551 - 4600',
                'min' => 4551,
                'max' => 4600
            ),
            182 => array(
                'label' => '4601 - 4650',
                'min' => 4601,
                'max' => 4650
            ),
            183 => array(
                'label' => '4651 - 4700',
                'min' => 4651,
                'max' => 4700
            ),
            184 => array(
                'label' => '4701 - 4750',
                'min' => 4701,
                'max' => 4750
            ),
            185 => array(
                'label' => '4751 - 4800',
                'min' => 4751,
                'max' => 4800
            ),
            186 => array(
                'label' => '4801 - 4850',
                'min' => 4801,
                'max' => 4850
            ),
            187 => array(
                'label' => '4851 - 4900',
                'min' => 4851,
                'max' => 4900
            ),
            188 => array(
                'label' => '4901 - 4950',
                'min' => 4901,
                'max' => 4950
            ),
            189 => array(
                'label' => '4951 - 5000',
                'min' => 4951,
                'max' => 5000
            ),
        );
    }

    /*
     * Get number of races category
     */
    public function get_number_of_races_category($number_of_races){
        $number_of_races_category = false;
        if($number_of_races){
            $number_of_races_categories = $this->get_number_of_races_categories();
            if($number_of_races_categories){
                foreach($number_of_races_categories as $nrc_key => $nrc){
                    if($number_of_races >= $nrc['min'] && $number_of_races <= $nrc['max']){
                        $number_of_races_category = array('key' => $nrc_key, 'label' => $nrc['label']);
                        break;
                    }
                }
            }
        }
        return $number_of_races_category;
    }

    /*
     * Outputs the scroll buttons on the league table templates
     */
    public function get_league_table_scroll_buttons(){
        ?>
        <div class="scroll_buttons">
            <div class="content-align-center">
                <h3 class="main-title">
                    <button class="left-scroll"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                    Scroll Stats
                    <button class="right-scroll"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                    <div class="clear"></div>
                </h3>
            </div>
        </div>
        <?php
    }

    /*
     * Get array of countries
     */
    public function get_countries(){
        return array(
            'GB' => 'United Kingdom',
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island & Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic Of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle Of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia And Sandwich Isl.',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard And Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'US' => 'United States',
            'UM' => 'United States Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        );
    }

    /*
     * Turn number into ordinal
     */
    public function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
        else
            return $number. $ends[$number % 10];
    }

    /*
     * Check user email is valid in WOCRL
     */
    public function validate_wocrl_email(){
        $wocrl_email = $this->get_wocrl_email();
        if($wocrl_email) {
            try {
                $vars = array(
                    'email' => $wocrl_email
                );
                $response = $this->client->request('GET', "checkuser/{$this->api_key}/", [
                    'query' => $vars
                ]);
                return $this->parse_guzzle_response($response);
            } catch (GuzzleException $e) {// RequestException
                return false;
            }
        }
        return false;
    }

    /*
     * Get users WOCRL email
     */
    public function get_wocrl_email(){
        $user = wp_get_current_user();
        if($user){
            $userdata = get_userdata($user->ID);
            $wocrl_email = $userdata->user_email;
            if($wocrl_email){
                return $wocrl_email;
            }
        }
        return false;
    }

    /*
     * Get users WOCRL user id
     */
    public function get_wocrl_user_id(){
        $wocrl_email = $this->get_wocrl_email();
        if($wocrl_email) {
            try {
                $vars = array(
                    'email' => $wocrl_email
                );
                $response = $this->client->request('GET', "getuserid/{$this->api_key}/", [
                    'query' => $vars
                ]);
                return $this->parse_guzzle_response($response);
            } catch (GuzzleException $e) {// RequestException
                return false;
            }
        }
        return false;
    }

    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="League Table Functions">

    /*
        * Get trophy hunter league table
        */
    public function get_th_league_table(){

        $race_league_points = 'trophy_hunter_running_points';

        $vars = array(
            'race_league' => 0
        );

        $non_filtered_race_stats = $this->get_leader_board($vars);
        $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

        ?>
        <div class="wocrl_league_output">
            <?php
            if($race_stats){

                // group by user id
                $all_race_stats_formatted = array();
                foreach($race_stats as $race_stat){

                    //calculate trophy hunter points for each race stat
                    $race_stat_id = $race_stat->race_stat_id;
                    $race_stat_event_id = $race_stat->event_id;
                    $race_stat_age_group = $race_stat->age_group;
                    $race_stat_gender = $race_stat->user_gender;

                    $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($this->calculate_overall_position_points($race_stat_id, $race_stat_event_id), $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group), $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender));

                    $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat_event_id);

                    $all_race_stats_formatted[$race_stat->user_id][] = $race_stat;

                    if(isset($all_race_stats_formatted[$race_stat->user_id]['total_points'])) {
                        $all_race_stats_formatted[$race_stat->user_id]['total_points'] = $all_race_stats_formatted[$race_stat->user_id]['total_points'] + $race_stat->{$race_league_points};
                    }else{
                        $all_race_stats_formatted[$race_stat->user_id]['total_points'] = $race_stat->{$race_league_points};
                    }
                }

                // Order all users races into correct position based on tiered scoring system
                if($all_race_stats_formatted){

                    foreach($all_race_stats_formatted as $race_stat_key => $race_stat){
                        $all_race_stats_formatted[$race_stat_key]['averages_totals'][$race_league_points] = $race_stat['total_points'];
                        $all_race_stats_formatted[$race_stat_key]['averages_totals']['hero_rating'] = $this->calculate_average(array_column($race_stat, 'hero_rating'));
                    }

                    // Should There Still Be One Or More Of The Same Scores, The Fastest Average Ocr Km/Mile Pace Will Be Used.
                    if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                        foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                            $all_race_stats_formatted[$race_stat_key]['averages_totals']['average_ocr_speed_kms'] = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($race_stat, 'average_ocr_speed_kms'))));
                        }
                        //Should There Still Be One Or More Of The Same Scores, The Highest Average Course Rating Will Be Used.
                        if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                            foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                $all_race_stats_formatted[$race_stat_key]['averages_totals']['course_rating'] = $this->calculate_average(array_column($race_stat, 'course_rating'));
                            }
                            // Should There Still Be One Or More Of The Same Scores, The Highest Average Weather Challenge Rating Will Be Used.
                            if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                                foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                    $all_race_stats_formatted[$race_stat_key]['averages_totals']['weather_rating'] = $this->calculate_average(array_column($race_stat, 'weather_rating'));
                                }
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'weather_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            } else {
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            }
                        } else {
                            array_multisort(
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                $all_race_stats_formatted);
                        }
                    } else {
                        // sort by highest overall average hero rating and trophy hunter points e.g.
                        // 1ST = RACER 1 = 76 POINTS + 100%
                        // 2ND = RACER 2 = 73 POINTS + 100%
                        // 3RD = RACER 4 = 60 POINTS + 100%
                        // 4TH = RACER 3 = 76 POINTS + 99%
                        // 5TH = RACER 5 = 75 POINTS + 99%
                        // 6TH = RACER 6 = 90 POINTS + 80%
                        array_multisort(
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                            $all_race_stats_formatted);
                    }

                }

                ?>

                <div class="one-column bgWhite page-row-pt league_tables">
                    <?php $this->get_league_table_scroll_buttons(); ?>
                    <div id="table-scroll" class="table-scroll">
                        <div class="table-wrap">
                            <table class="main-table">

                                <thead>
                                <tr>
                                    <th class="fixed-side">#</th>
                                    <th class="fixed-side">Racer</th>
                                    <th>Number of<br />Races</th>
                                    <th>Total<br />TH Points</th>
                                    <th>Total<br />FR Points</th>
                                    <th>Average<br />Hero Rating</th>
                                    <th>Average OCR<br />Pace (KMS)</th>
                                    <th>Average OCR<br />Pace (MILES)</th>
                                    <th>Average<br />Course Rating</th>
                                    <th>Average<br />Obstacle Challenge</th>
                                    <th>Average<br />Terrain Challenge</th>
                                    <th>Average Weather<br />Challenge Level</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                $position = 1;
                                $overall_total_fr_points = 0;
                                $overall_total_number_of_races = 0;
                                foreach($all_race_stats_formatted as $formatted_race_stat_key => $formatted_race_stat){

                                    // minus 2 because we don't want to include 'total_points' or 'averages_totals' field
                                    $number_of_races = count($all_race_stats_formatted[$formatted_race_stat_key]) - 2;
                                    $overall_total_number_of_races = $overall_total_number_of_races + $number_of_races;

                                    // if we are filtering by number of races, skip any that aren't a match
                                    if(isset($_GET['filter_number_of_races']) && $_GET['filter_number_of_races'] != '') {
                                        $number_of_races_category = $this->get_number_of_races_category($number_of_races);
                                        if ($number_of_races_category['key'] != $_GET['filter_number_of_races']) {
                                            unset($all_race_stats_formatted[$formatted_race_stat_key]);
                                            continue;
                                        }
                                    }

                                    $user_id = $formatted_race_stat[0]->user_id;
                                    $user_first_name = $formatted_race_stat[0]->user_first_name;
                                    $user_last_name = $formatted_race_stat[0]->user_last_name;
                                    $total_points = $formatted_race_stat['total_points'];

                                    $total_fr_points = array_sum(array_column($formatted_race_stat, 'fun_running_points'));
                                    $overall_total_fr_points = $overall_total_fr_points + $total_fr_points;

                                    /* Averages from all races per user */
                                    $average_hero_rating = $this->calculate_average(array_column($formatted_race_stat, 'hero_rating'));
                                    $average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_kms')));
                                    $average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_miles')));
                                    $average_course_rating = $this->calculate_average(array_column($formatted_race_stat, 'course_rating'));
                                    $average_obstacle_challenge = $this->calculate_average(array_column($formatted_race_stat, 'obstacle_challenge_levels'));
                                    $average_terrain_challenge = $this->calculate_average(array_column($formatted_race_stat, 'terrain_challenge_levels'));
                                    $average_weather_factor_challenge = $this->calculate_average(array_column($formatted_race_stat, 'main_weather_factor_challenge_levels'));

                                    ?>

                                    <tr data-user-id="<?php echo $user_id; ?>">
                                        <td class="fixed-side"><?php echo $this->ordinal($position); ?></td>
                                        <td class="fixed-side"><?php echo $user_first_name[0]; ?>.<?php echo $user_last_name; ?></td>
                                        <td><?php echo $number_of_races; ?></td>
                                        <td><?php echo $total_points; ?></td>
                                        <td><?php echo $total_fr_points; ?></td>
                                        <td><?php echo number_format(ceil(($average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_weather_factor_challenge); ?>/9</td>
                                    </tr>

                                    <?php

                                    $position++;
                                }
                                ?>
                                </tbody>

                                <?php

                                /* Overall averages from all races for all users */
                                $overall_total_points = array_sum(array_column($all_race_stats_formatted, 'total_points'));
                                $overall_average_hero_rating = $this->calculate_overall_average($all_race_stats_formatted, 'hero_rating');
                                $overall_average_ocr_speed_kms = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_kms');
                                $overall_average_ocr_speed_miles = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_miles');
                                $overall_average_course_rating = $this->calculate_overall_average($all_race_stats_formatted, 'course_rating');
                                $overall_average_obstacle_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'obstacle_challenge_levels');
                                $overall_average_terrain_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'terrain_challenge_levels');
                                $overall_average_weather_factor_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'main_weather_factor_challenge_levels');
                                ?>
                                <tfoot>
                                <tr>
                                    <td class="fixed-side"></td>
                                    <td class="fixed-side"></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_number_of_races : 0; ?></td>
                                    <td><?php echo $overall_total_points; ?></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_fr_points : 0; ?></td>
                                    <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                    <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                    <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                </tr>

                                </tfoot>

                            </table>
                        </div>
                    </div>
                </div>

                <?php
            }else{
                ?>
                <p class="site-width page-row-pb page-row-pt">No races found for this league</p>
                <?php
            }
            ?>
            <div class="one-column page-row-pt bgNavy filters">
                <div class="site-width">
                    <!-- output filter form here --->
                    <?php $this->get_league_table_filter_form(0); ?>
                </div>
            </div>
        </div>

        <?php
    }

    /*
    * Get fun runner league table
    */
    public function get_fr_league_table(){

        $race_league_points = 'fun_running_points';

        $vars = array(
            'race_league' => 1
        );

        $non_filtered_race_stats = $this->get_leader_board($vars);
        $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

        ?>
        <div class="wocrl_league_output">
            <?php
            if($race_stats){

                // group by user id
                $all_race_stats_formatted = array();
                foreach($race_stats as $race_stat){

                    $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat->event_id);

                    $all_race_stats_formatted[$race_stat->user_id][] = $race_stat;

                    // add up total points (used for ordering the results by highest points first)
                    if(isset($all_race_stats_formatted[$race_stat->user_id]['total_points'])) {
                        $all_race_stats_formatted[$race_stat->user_id]['total_points'] = $all_race_stats_formatted[$race_stat->user_id]['total_points'] + $race_stat->{$race_league_points};
                    }else{
                        $all_race_stats_formatted[$race_stat->user_id]['total_points'] = $race_stat->{$race_league_points};
                    }
                }

                // Order all users races into correct position based on tiered scoring system
                if($all_race_stats_formatted){

                    foreach($all_race_stats_formatted as $race_stat_key => $race_stat){
                        $all_race_stats_formatted[$race_stat_key]['averages_totals'][$race_league_points] = $race_stat['total_points'];
                        $all_race_stats_formatted[$race_stat_key]['averages_totals']['hero_rating'] = $this->calculate_average(array_column($race_stat, 'hero_rating'));
                    }

                    // Should There Still Be One Or More Of The Same Scores, The Fastest Average Ocr Km/Mile Pace Will Be Used.
                    if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                        foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                            $all_race_stats_formatted[$race_stat_key]['averages_totals']['average_ocr_speed_kms'] = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($race_stat, 'average_ocr_speed_kms'))));
                        }
                        //Should There Still Be One Or More Of The Same Scores, The Highest Average Course Rating Will Be Used.
                        if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                            foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                $all_race_stats_formatted[$race_stat_key]['averages_totals']['course_rating'] = $this->calculate_average(array_column($race_stat, 'course_rating'));
                            }
                            // Should There Still Be One Or More Of The Same Scores, The Highest Average Weather Challenge Rating Will Be Used.
                            if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                                foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                    $all_race_stats_formatted[$race_stat_key]['averages_totals']['weather_rating'] = $this->calculate_average(array_column($race_stat, 'weather_rating'));
                                }
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'weather_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            } else {
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            }
                        } else {
                            array_multisort(
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                $all_race_stats_formatted);
                        }
                    } else {
                        // sort by highest overall average hero rating and fun running points e.g.
                        // 1ST = RACER 1 = 76 POINTS + 100%
                        // 2ND = RACER 2 = 73 POINTS + 100%
                        // 3RD = RACER 4 = 60 POINTS + 100%
                        // 4TH = RACER 3 = 76 POINTS + 99%
                        // 5TH = RACER 5 = 75 POINTS + 99%
                        // 6TH = RACER 6 = 90 POINTS + 80%
                        array_multisort(
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                            $all_race_stats_formatted);
                    }

                }

                ?>

                <div class="one-column bgWhite page-row-pt league_tables">
                    <?php $this->get_league_table_scroll_buttons(); ?>
                    <div id="table-scroll" class="table-scroll">
                        <div class="table-wrap">
                            <table class="main-table">

                                <thead>
                                <tr>
                                    <th class="fixed-side">#</th>
                                    <th class="fixed-side">Racer</th>
                                    <th>Number of<br />Races</th>
                                    <th>Total<br />FR Points</th>
                                    <th>Total<br />Obstacles Completed</th>
                                    <th>Total<br />Miles Covered</th>
                                    <th>Total<br />KMs Covered</th>
                                    <th>Average<br />Hero Rating</th>
                                    <th>Average OCR<br />Pace (KMS)</th>
                                    <th>Average OCR<br />Pace (MILES)</th>
                                    <th>Average<br />Course Rating</th>
                                    <th>Average<br />Obstacle Challenge</th>
                                    <th>Average<br />Terrain Challenge</th>
                                    <th>Average Weather<br />Challenge Level</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                $position = 1;
                                $overall_total_number_of_races = 0;
                                $overall_total_obstacles = 0;
                                $overall_total_miles = 0;
                                $overall_total_kms = 0;
                                foreach($all_race_stats_formatted as $formatted_race_stat_key => $formatted_race_stat){

                                    // minus 2 because we don't want to include 'total_points' or 'averages_totals' field
                                    $number_of_races = count($all_race_stats_formatted[$formatted_race_stat_key]) - 2;
                                    $overall_total_number_of_races = $overall_total_number_of_races + $number_of_races;

                                    // if we are filtering by number of races, skip any that aren't a match
                                    if(isset($_GET['filter_number_of_races']) && $_GET['filter_number_of_races'] != '') {
                                        $number_of_races_category = $this->get_number_of_races_category($number_of_races);
                                        if ($number_of_races_category['key'] != $_GET['filter_number_of_races']) {
                                            unset($all_race_stats_formatted[$formatted_race_stat_key]);
                                            continue;
                                        }
                                    }

                                    $user_id = $formatted_race_stat[0]->user_id;
                                    $user_first_name = $formatted_race_stat[0]->user_first_name;
                                    $user_last_name = $formatted_race_stat[0]->user_last_name;

                                    $total_points = $formatted_race_stat['total_points'];

                                    $total_obstacles = array_sum(array_column($formatted_race_stat, 'number_of_obstacles'));
                                    $total_miles = array_sum(array_column($formatted_race_stat, 'total_distance_miles'));
                                    $total_kms = array_sum(array_column($formatted_race_stat, 'total_distance_kms'));

                                    $overall_total_obstacles = $overall_total_obstacles + $total_obstacles;
                                    $overall_total_miles     = $overall_total_miles + $total_miles;
                                    $overall_total_kms     = $overall_total_kms + $total_kms;

                                    /* Averages from all races per user */
                                    $average_hero_rating = $this->calculate_average(array_column($formatted_race_stat, 'hero_rating'));
                                    $average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_kms')));
                                    $average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_miles')));
                                    $average_course_rating = $this->calculate_average(array_column($formatted_race_stat, 'course_rating'));
                                    $average_obstacle_challenge = $this->calculate_average(array_column($formatted_race_stat, 'obstacle_challenge_levels'));
                                    $average_terrain_challenge = $this->calculate_average(array_column($formatted_race_stat, 'terrain_challenge_levels'));
                                    $average_weather_factor_challenge = $this->calculate_average(array_column($formatted_race_stat, 'main_weather_factor_challenge_levels'));

                                    ?>

                                    <tr data-user-id="<?php echo $user_id; ?>">
                                        <td class="fixed-side"><?php echo $this->ordinal($position); ?></td>
                                        <td class="fixed-side"><?php echo $user_first_name[0]; ?>.<?php echo $user_last_name; ?></td>
                                        <td><?php echo $number_of_races; ?></td>
                                        <td><?php echo $total_points; ?></td>
                                        <td><?php echo $total_obstacles; ?></td>
                                        <td><?php echo number_format($total_miles, 2); ?></td>
                                        <td><?php echo number_format($total_kms, 2); ?></td>
                                        <td><?php echo number_format(ceil(($average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_weather_factor_challenge); ?>/9</td>
                                    </tr>

                                    <?php

                                    $position++;
                                }
                                ?>
                                </tbody>

                                <?php

                                /* Overall averages from all races for all users */
                                $overall_total_points = array_sum(array_column($all_race_stats_formatted, 'total_points'));
                                $overall_average_hero_rating = $this->calculate_overall_average($all_race_stats_formatted, 'hero_rating');
                                $overall_average_ocr_speed_kms = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_kms');
                                $overall_average_ocr_speed_miles = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_miles');
                                $overall_average_course_rating = $this->calculate_overall_average($all_race_stats_formatted, 'course_rating');
                                $overall_average_obstacle_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'obstacle_challenge_levels');
                                $overall_average_terrain_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'terrain_challenge_levels');
                                $overall_average_weather_factor_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'main_weather_factor_challenge_levels');
                                ?>
                                <tfoot>
                                <tr>
                                    <td class="fixed-side"></td>
                                    <td class="fixed-side"></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_number_of_races : 0; ?></td>
                                    <td><?php echo $overall_total_points; ?></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_obstacles : 0; ?></td>
                                    <td><?php echo ($all_race_stats_formatted) ? number_format($overall_total_miles, 2) : 0; ?></td>
                                    <td><?php echo ($all_race_stats_formatted) ? number_format($overall_total_kms, 2) : 0; ?></td>
                                    <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                    <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                    <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                </tr>

                                </tfoot>

                            </table>
                        </div>
                    </div>
                </div>

                <?php
            }else{
                ?>
                <p class="site-width page-row-pt page-row-pb">No races found for this league</p>
                <?php
            }
            ?>
            <div class="one-column page-row-pt bgNavy filters">
                <div class="site-width">
                    <!-- output filter form here --->
                    <?php $this->get_league_table_filter_form(1); ?>
                </div>
            </div>

        </div>

        <?php
    }

    /*
     * Get community league table
     */
    public function get_community_league_table(){
        $race_league_points = 'trophy_hunter_running_points';

        $vars = array(
                'community_id' => true
        );
        $non_filtered_race_stats = $this->get_leader_board($vars);
        $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

        ?>
        <div class="wocrl_league_output">
            <?php
            if($race_stats){

                // group by community id
                $all_race_stats_formatted = array();
                foreach($race_stats as $race_stat){


                    //calculate trophy hunter points for each race stat
                    $race_stat_id = $race_stat->race_stat_id;
                    $race_stat_event_id = $race_stat->event_id;
                    $race_stat_age_group = $race_stat->age_group;
                    $race_stat_gender = $race_stat->user_gender;

                    $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($this->calculate_overall_position_points($race_stat_id, $race_stat_event_id), $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group), $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender));

                    $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat_event_id);

                    $all_race_stats_formatted[$race_stat->community_id][] = $race_stat;

                    // add up total th points (used for ordering the results by highest points first)
                    if(isset($all_race_stats_formatted[$race_stat->community_id]['total_points'])) {
                        $all_race_stats_formatted[$race_stat->community_id]['total_points'] = $all_race_stats_formatted[$race_stat->community_id]['total_points'] + $race_stat->{$race_league_points};
                    }else{
                        $all_race_stats_formatted[$race_stat->community_id]['total_points'] = $race_stat->{$race_league_points};
                    }
                }

                // Order all users races into correct position based on tiered scoring system
                if($all_race_stats_formatted){

                    foreach($all_race_stats_formatted as $race_stat_key => $race_stat){
                        $all_race_stats_formatted[$race_stat_key]['averages_totals'][$race_league_points] = $race_stat['total_points'];
                    }

                    // Should There Still Be One Or More Of The Same Scores, The Highest Total Fun Running Points Will Be Used
                    if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                        foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                            $all_race_stats_formatted[$race_stat_key]['averages_totals']['fun_running_points'] = array_sum(array_column($race_stat, 'fun_running_points'));
                        }
                        // Should There Still Be One Or More Of The Same Scores, The Highest Average Hero Rating Will Be Used
                        if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'hero_rating'), SORT_REGULAR))) {
                            foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                $all_race_stats_formatted[$race_stat_key]['averages_totals']['hero_rating'] = $this->calculate_average(array_column($race_stat, 'hero_rating'));
                            }
                        } else {
                            array_multisort(
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'fun_running_points'), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                $all_race_stats_formatted);
                        }
                    } else {
                        // sort by highest trophy hunter points
                        array_multisort(
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                            $all_race_stats_formatted);
                    }

                }

                ?>

                <div class="one-column bgWhite page-row-pt league_tables">
                    <?php $this->get_league_table_scroll_buttons(); ?>
                    <div id="table-scroll" class="table-scroll">
                        <div class="table-wrap">
                            <table class="main-table">

                                <thead>
                                <tr>
                                    <th class="fixed-side">#</th>
                                    <th class="fixed-side">Community</th>
                                    <th>Number of<br />Races</th>
                                    <th>Total<br />TH Points</th>
                                    <th>Total<br />FR Points</th>
                                    <th>Average<br />Hero Rating</th>
                                    <th>Average OCR<br />Pace (KMS)</th>
                                    <th>Average OCR<br />Pace (MILES)</th>
                                    <th>Average<br />Course Rating</th>
                                    <th>Average<br />Obstacle Challenge</th>
                                    <th>Average<br />Terrain Challenge</th>
                                    <th>Average Weather<br />Factor Challenge</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                $position = 1;
                                $overall_total_number_of_races = 0;
                                $overall_total_fr_points = 0;
                                foreach($all_race_stats_formatted as $formatted_race_stat_key => $formatted_race_stat){


                                    // minus one so we don't include the 'total_points' or 'average_totals'
                                    $number_of_races = count($all_race_stats_formatted[$formatted_race_stat_key]) - 2;
                                    $overall_total_number_of_races = $overall_total_number_of_races + $number_of_races;

                                    // if we are filtering by number of races, skip any that aren't a match
                                    if(isset($_GET['filter_number_of_races']) && $_GET['filter_number_of_races'] != '') {
                                        $number_of_races_category = $this->get_number_of_races_category($number_of_races);
                                        if ($number_of_races_category['key'] != $_GET['filter_number_of_races']) {
                                            unset($all_race_stats_formatted[$formatted_race_stat_key]);
                                            continue;
                                        }
                                    }

                                    $total_th_points = $formatted_race_stat['total_points'];

                                    $total_fr_points = array_sum(array_column($formatted_race_stat, 'fun_running_points'));
                                    $overall_total_fr_points = $overall_total_fr_points + $total_fr_points;

                                    /* Averages from all races per user */
                                    $average_hero_rating = $this->calculate_average(array_column($formatted_race_stat, 'hero_rating'));
                                    $average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_kms')));
                                    $average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_miles')));
                                    $average_course_rating = $this->calculate_average(array_column($formatted_race_stat, 'course_rating'));
                                    $average_obstacle_challenge = $this->calculate_average(array_column($formatted_race_stat, 'obstacle_challenge_levels'));
                                    $average_terrain_challenge = $this->calculate_average(array_column($formatted_race_stat, 'terrain_challenge_levels'));
                                    $average_weather_factor_challenge = $this->calculate_average(array_column($formatted_race_stat, 'main_weather_factor_challenge_levels'));


                                    ?>

                                    <tr>
                                        <td class="fixed-side"><?php echo $this->ordinal($position); ?></td>
                                        <td class="fixed-side"><?php echo $formatted_race_stat[0]->community_name; ?></td>
                                        <td><?php echo $number_of_races; ?></td>
                                        <td><?php echo $total_th_points; ?></td>
                                        <td><?php echo $total_fr_points; ?></td>
                                        <td><?php echo number_format(ceil(($average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_weather_factor_challenge); ?>/9</td>
                                    </tr>

                                    <?php

                                    $position++;
                                }
                                ?>
                                </tbody>

                                <?php
                                /* Overall averages from all races for all users */
                                $overall_total_th_points = array_sum(array_column($all_race_stats_formatted, 'total_points'));
                                $overall_average_hero_rating = $this->calculate_overall_average($all_race_stats_formatted, 'hero_rating');
                                $overall_average_ocr_speed_kms = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_kms');
                                $overall_average_ocr_speed_miles = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_miles');
                                $overall_average_course_rating = $this->calculate_overall_average($all_race_stats_formatted, 'course_rating');
                                $overall_average_obstacle_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'obstacle_challenge_levels');
                                $overall_average_terrain_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'terrain_challenge_levels');
                                $overall_average_weather_factor_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'main_weather_factor_challenge_levels');
                                ?>
                                <tfoot>
                                <tr>
                                    <td class="fixed-side"></td>
                                    <td class="fixed-side"></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_number_of_races : 0; ?></td>
                                    <td><?php echo $overall_total_th_points; ?></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_fr_points : 0; ?></td>
                                    <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                    <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                    <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                </tr>

                                </tfoot>

                            </table>
                        </div>
                    </div>
                </div>

                <?php
            }else{
                ?>
                <p class="site-width page-row-pb page-row-pt">No races found for this league</p>
                <?php
            }

            ?>
            <div class="one-column page-row-pt bgNavy filters">
                <div class="site-width">
                    <!-- output filter form here --->
                    <?php $this->get_league_table_filter_form(3); ?>
                </div>
            </div>

        </div>

        <?php
    }

    /*
     * Get race directors league table
     */
    public function get_race_directors_league_table(){
        $race_league_points = 'trophy_hunter_running_points';

        $vars = array();
        $non_filtered_race_stats = $this->get_leader_board($vars);
        $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

        ?>
        <div class="wocrl_league_output">
            <?php
            if($race_stats){

                // group by event id
                $all_race_stats_formatted = array();
                foreach($race_stats as $race_stat){

                    //calculate trophy hunter points for each race stat
                    $race_stat_id = $race_stat->race_stat_id;
                    $race_stat_event_id = $race_stat->event_id;
                    $race_stat_age_group = $race_stat->age_group;
                    $race_stat_gender = $race_stat->user_gender;

                    $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($this->calculate_overall_position_points($race_stat_id, $race_stat_event_id), $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group), $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender));

                    $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat_event_id);

                    $all_race_stats_formatted[$race_stat->event_id][] = $race_stat;
                }


                // Order all events races into correct position based on average course rating
                if($all_race_stats_formatted){

                    foreach($all_race_stats_formatted as $race_stat_key => $race_stat){
                        $all_race_stats_formatted[$race_stat_key]['averages_totals']['course_rating'] = $this->calculate_average(array_column($race_stat, 'course_rating'));
                    }
                    array_multisort(
                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                        $all_race_stats_formatted);
                }

                ?>

                <div class="one-column bgWhite page-row-pt league_tables">
                    <?php $this->get_league_table_scroll_buttons(); ?>
                    <div id="table-scroll" class="table-scroll">
                        <div class="table-wrap">
                            <table class="main-table">

                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th class="fixed-side">Race</th>
                                    <th>Number<br />
                                        of Races</th>
                                    <th>Average<br />Hero Rating</th>
                                    <th>Average OCR<br />Pace (KMS)</th>
                                    <th>Average OCR<br />Pace (MILES)</th>
                                    <th>Average<br />Course Rating</th>
                                    <th>Average<br />Obstacle Challenge</th>
                                    <th>Average<br />Obstacle Creativity</th>
                                    <th>Average<br />Terrain Challenge</th>
                                    <th>Average<br />Terrain Creativity</th>
                                    <th>Average Weather<br />Factor Challenge</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                $position = 0;
                                $overall_total_number_of_races = 0;
                                $previous_average_course_rating = 0;
                                foreach($all_race_stats_formatted as $formatted_race_stat_key => $formatted_race_stat){

                                    $number_of_races = count($all_race_stats_formatted[$formatted_race_stat_key]) - 1;
                                    $overall_total_number_of_races = $overall_total_number_of_races + $number_of_races;

                                    // if we are filtering by number of races, skip any that aren't a match
                                    if(isset($_GET['filter_number_of_races']) && $_GET['filter_number_of_races'] != '') {
                                        $number_of_races_category = $this->get_number_of_races_category($number_of_races);
                                        if ($number_of_races_category['key'] != $_GET['filter_number_of_races']) {
                                            unset($all_race_stats_formatted[$formatted_race_stat_key]);
                                            continue;
                                        }
                                    }

                                    /* Averages from all races per user */
                                    $average_hero_rating = $this->calculate_average(array_column($formatted_race_stat, 'hero_rating'));
                                    $average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_kms')));
                                    $average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($formatted_race_stat, 'average_ocr_speed_miles')));
                                    $average_course_rating = ceil($this->calculate_average(array_column($formatted_race_stat, 'course_rating')));
                                    $average_obstacle_challenge = $this->calculate_average(array_column($formatted_race_stat, 'obstacle_challenge_levels'));
                                    $average_obstacle_creativity = $this->calculate_average(array_column($formatted_race_stat, 'obstacle_creativity_levels'));
                                    $average_terrain_challenge = $this->calculate_average(array_column($formatted_race_stat, 'terrain_challenge_levels'));
                                    $average_terrain_creativity = $this->calculate_average(array_column($formatted_race_stat, 'terrain_creativity_levels'));
                                    $average_weather_factor_challenge = $this->calculate_average(array_column($formatted_race_stat, 'main_weather_factor_challenge_levels'));

                                    // break race name onto multiple lines so it doesn't break table scrolling on mobile
                                    $race_name=  $formatted_race_stat[0]->event_title;
                                    $x = '15';
                                    $race_name_formatted = explode( "\n", wordwrap( $race_name, $x));


                                    // if events have same average course rating, they get same position
                                    if($previous_average_course_rating != $average_course_rating) {
                                        $position++;
                                    }
                                    $previous_average_course_rating = $average_course_rating;

                                    ?>

                                    <tr>
                                        <td><?php echo $this->ordinal($position); ?></td>
                                        <td class="fixed-side"><?php echo implode('<br>', $race_name_formatted); ?></td>
                                        <td><?php echo $number_of_races; ?></td>
                                        <td><?php echo number_format(ceil(($average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $average_ocr_speed_miles); ?></td>
                                        <td><?php echo $average_course_rating; ?>/40</td>
                                        <td><?php echo ceil($average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_obstacle_creativity); ?>/10</td>
                                        <td><?php echo ceil($average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($average_terrain_creativity); ?>/10</td>
                                        <td><?php echo ceil($average_weather_factor_challenge); ?>/9</td>
                                    </tr>

                                    <?php
                                }
                                ?>
                                </tbody>

                                <?php
                                /* Overall averages from all races for all users */
                                $overall_average_hero_rating = $this->calculate_overall_average($all_race_stats_formatted, 'hero_rating');
                                $overall_average_ocr_speed_kms = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_kms');
                                $overall_average_ocr_speed_miles = $this->calculate_overall_average($all_race_stats_formatted, 'average_ocr_speed_miles');
                                $overall_average_course_rating = $this->calculate_overall_average($all_race_stats_formatted, 'course_rating');
                                $overall_average_obstacle_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'obstacle_challenge_levels');
                                $overall_average_obstacle_creativity = $this->calculate_overall_average($all_race_stats_formatted, 'obstacle_creativity_levels');
                                $overall_average_terrain_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'terrain_challenge_levels');
                                $overall_average_terrain_creativity = $this->calculate_overall_average($all_race_stats_formatted, 'terrain_creativity_levels');
                                $overall_average_weather_factor_challenge = $this->calculate_overall_average($all_race_stats_formatted, 'main_weather_factor_challenge_levels');
                                ?>
                                <tfoot>
                                <tr>
                                    <td></td>
                                    <td class="fixed-side"></td>
                                    <td><?php echo ($all_race_stats_formatted) ? $overall_total_number_of_races : 0; ?></td>
                                    <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                    <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                    <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_obstacle_creativity); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_creativity); ?>/10</td>
                                    <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                </tr>

                                </tfoot>

                            </table>
                        </div>
                    </div>
                </div>

                <?php
            }else{
                ?>
                <p class="site-width page-row-pt page-row-pb">No races found for this league</p>
                <?php
            }

            ?>
            <div class="one-column page-row-pt bgNavy filters">
                <div class="site-width">
                    <!-- output filter form here --->
                    <?php $this->get_league_table_filter_form(2); ?>
                </div>
            </div>

        </div>

        <?php
    }

    /*
     * Get users fun runners race history table
     */
    public function get_fr_race_history_table(){
        @session_start();

        $can_access = $this->can_access();
        if(!is_wp_error($can_access)) {
            $race_league_id = 1;

            $vars = array(
                'race_league' => 1,
                'user_id' => $this->get_wocrl_user_id()
            );

            $non_filtered_race_stats = $this->get_leader_board($vars);
            $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

            ?>
            <div class="wocrl_league_output">
                <?php
                if($race_stats){

                    ?>

                    <div class="one-column bgWhite page-row-pt league_tables">
                        <?php $this->get_league_table_scroll_buttons(); ?>
                        <div id="table-scroll" class="table-scroll">
                            <div class="table-wrap">
                                <table class="main-table">

                                    <thead>
                                    <tr>
                                        <th class="fixed-side">Race</th>
                                        <th>Date</th>
                                        <th>Total<br />Miles Covered</th>
                                        <th>Total<br />KMs Covered</th>
                                        <th>Hero<br />Rating</th>
                                        <th>Total<br/ >Race Time</th>
                                        <th>Average<br />OCR Speed (KMS)</th>
                                        <th>Average<br />OCR Speed (MILES)</th>
                                        <th>Course<br />Rating</th>
                                        <th>Obstacle<br />Challenge</th>
                                        <th>Terrain<br />Challenge</th>
                                        <th>Weather<br />Challenge Level</th>
                                        <th>Main<br />Weather Factor</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    $position = 1;
                                    foreach($race_stats as $race_stat){

                                        // break race name onto multiple lines so it doesn't break table scrolling on mobile
                                        $race_name=  $race_stat->event_title;
                                        $x = '15';
                                        $race_name_formatted = explode( "\n", wordwrap( $race_name, $x));
                                        ?>

                                        <tr>
                                            <td class="fixed-side"><?php echo implode('<br>', $race_name_formatted); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($race_stat->event_start_date)); ?></td>
                                            <td><?php echo number_format($race_stat->total_distance_miles, 2); ?></td>
                                            <td><?php echo number_format($race_stat->total_distance_kms, 2); ?></td>
                                            <td><?php echo number_format(ceil(($race_stat->hero_rating * 100)), 2);?>%</td>
                                            <td><?php echo $race_stat->overall_time; ?></td>
                                            <td><?php echo $race_stat->average_ocr_speed_kms; ?></td>
                                            <td><?php echo $race_stat->average_ocr_speed_miles; ?></td>
                                            <td><?php echo ceil($race_stat->course_rating); ?>/40</td>
                                            <td><?php echo ceil($race_stat->obstacle_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($race_stat->terrain_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($race_stat->main_weather_factor_challenge_levels); ?>/9</td>
                                            <td><?php echo $race_stat->main_weather_factor; ?></td>
                                        </tr>

                                        <?php
                                        $position++;
                                    }
                                    ?>
                                    </tbody>

                                    <?php
                                    /* Overall averages from all races  */
                                    $overall_total_distance_miles = array_sum(array_column($race_stats, 'total_distance_miles'));
                                    $overall_total_distance_kms = array_sum(array_column($race_stats, 'total_distance_kms'));
                                    $overall_average_hero_rating = $this->calculate_average(array_column($race_stats, 'hero_rating'));
                                    $overall_average_total_race_time = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'overall_time')));
                                    $overall_average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_kms')));
                                    $overall_average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_miles')));
                                    $overall_average_course_rating = $this->calculate_average(array_column($race_stats, 'course_rating'));
                                    $overall_average_obstacle_challenge = $this->calculate_average(array_column($race_stats, 'obstacle_challenge_levels'));
                                    $overall_average_terrain_challenge = $this->calculate_average(array_column($race_stats, 'terrain_challenge_levels'));
                                    $overall_average_weather_factor_challenge = $this->calculate_average(array_column($race_stats, 'main_weather_factor_challenge_levels'));
                                    ?>
                                    <tfoot>
                                    <tr>
                                        <td class="fixed-side"></td>
                                        <td></td>
                                        <td><?php echo number_format($overall_total_distance_miles, 2); ?></td>
                                        <td><?php echo number_format($overall_total_distance_kms, 2); ?></td>
                                        <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo date('H:i:s', $overall_average_total_race_time); ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                        <td></td>
                                    </tr>

                                    </tfoot>

                                </table>
                            </div>
                        </div>
                    </div>

                    <?php
                }else{
                    ?>
                    <p class="site-width page-row-pt page-row-pb">No race history found</p>
                    <?php
                }
                ?>
                <div class="one-column page-row-pt bgNavy filters">
                    <div class="site-width">
                        <!-- output filter form here --->
                        <?php $this->get_personal_history_filter_form(1); ?>
                    </div>
                </div>
            </div>
        <?php
        }else{
          ?>
            <p class="site-width page-row-pt page-row-pb"><?php echo $can_access->get_error_message(); ?></p>
            <?php
        }
    }

    /*
     * Get users trophy hunters race history table
     */
    public function get_th_race_history_table(){
        @session_start();

        $can_access = $this->can_access();
        if(!is_wp_error($can_access)) {
            $race_league_points = 'trophy_hunter_running_points';

            $vars = array(
                'race_league' => 0,
                'user_id' => $this->get_wocrl_user_id()
            );

            $non_filtered_race_stats = $this->get_leader_board($vars);
            $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

            ?>
            <div class="wocrl_league_output">
                <?php
                if($race_stats){

                    // set position points for each race
                    foreach($race_stats as $race_stat_key => $race_stat) {
                        //calculate trophy hunter points for each race stat
                        $race_stat_id = $race_stat->race_stat_id;
                        $race_stat_event_id = $race_stat->event_id;
                        $race_stat_age_group = $race_stat->age_group;
                        $race_stat_gender = $race_stat->user_gender;

                        $race_stat->overall_position_points = $this->calculate_overall_position_points($race_stat_id, $race_stat_event_id);
                        $race_stat->age_position_points = $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group);
                        $race_stat->gender_position_points = $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender);

                        $race_stat->overall_position = $this->calculate_overall_position($race_stat_id, $race_stat_event_id);
                        $race_stat->age_position = $this->calculate_age_group_position($race_stat_id, $race_stat_event_id, $race_stat_age_group);
                        $race_stat->gender_position = $this->calculate_gender_position($race_stat_id, $race_stat_event_id, $race_stat_gender);

                        $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($race_stat->overall_position_points, $race_stat->age_position_points, $race_stat->gender_position_points);
                    }

                    ?>

                    <div class="one-column bgWhite page-row-pt league_tables">
                        <?php $this->get_league_table_scroll_buttons(); ?>
                        <div id="table-scroll" class="table-scroll">
                            <div class="table-wrap">
                                <table class="main-table">

                                    <thead>
                                    <tr>
                                        <th class="fixed-side">Race</th>
                                        <th>Date</th>
                                        <th>Overall<br />Position</th>
                                        <th>Position<br />Points</th>
                                        <th>Age Group<br />Position</th>
                                        <th>Position<br />Points</th>
                                        <th>Gender<br />Position</th>
                                        <th>Position<br />Points</th>
                                        <th>Total<br />TH Points</th>
                                        <th>Total<br />Race Time</th>
                                        <th>Hero<br />Rating</th>
                                        <th>Total Distance (KMs)</th>
                                        <th>Total Distance (Miles)</th>
                                        <th>Challenge<br />Lane Taken</th>
                                        <th>Average OCR<br />Pace (KMS)</th>
                                        <th>Average OCR<br />Pace (MILES)</th>
                                        <th>Course<br />Rating</th>
                                        <th>Obstacle<br />Challenge</th>
                                        <th>Terrain<br />Challenge</th>
                                        <th>Weather<br />Challenge Level</th>
                                        <th>Main<br />Weather Factor</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    $position = 1;

                                    foreach($race_stats as $race_stat){

                                        // break race name onto multiple lines so it doesn't break table scrolling on mobile
                                        $race_name=  $race_stat->event_title;
                                        $x = '15';
                                        $race_name_formatted = explode( "\n", wordwrap( $race_name, $x));

                                        ?>

                                        <tr>
                                            <td class="fixed-side"><?php echo implode('<br>', $race_name_formatted); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($race_stat->event_start_date)); ?></td>
                                            <td><?php echo $this->ordinal($race_stat->overall_position); ?></td>
                                            <td><?php echo $race_stat->overall_position_points; ?>/30</td>
                                            <td><?php echo $this->ordinal($race_stat->age_position); ?></td>
                                            <td><?php echo $race_stat->age_position_points; ?>/30</td>
                                            <td><?php echo $this->ordinal($race_stat->gender_position); ?></td>
                                            <td><?php echo $race_stat->gender_position_points; ?>/30</td>
                                            <td><?php echo $race_stat->{$race_league_points}; ?></td>
                                            <td><?php echo $race_stat->overall_time; ?></td>
                                            <td><?php echo number_format(ceil(($race_stat->hero_rating * 100)), 2);?>%</td>
                                            <td><?php echo number_format($race_stat->total_distance_kms, 2);?></td>
                                            <td><?php echo number_format($race_stat->total_distance_miles, 2);?></td>
                                            <td><?php echo $race_stat->challenge_lane; ?></td>
                                            <td><?php echo $race_stat->average_ocr_speed_kms; ?></td>
                                            <td><?php echo $race_stat->average_ocr_speed_miles; ?></td>
                                            <td><?php echo ceil($race_stat->course_rating); ?>/40</td>
                                            <td><?php echo ceil($race_stat->obstacle_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($race_stat->terrain_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($race_stat->main_weather_factor_challenge_levels); ?>/9</td>
                                            <td><?php echo $race_stat->main_weather_factor; ?></td>
                                        </tr>

                                        <?php
                                        $position++;
                                    }
                                    ?>
                                    </tbody>

                                    <?php
                                    /* Overall averages from all races  */
                                    $overall_total_points = array_sum(array_column($race_stats, $race_league_points));
                                    $overall_average_overall_position = $this->calculate_average(array_column($race_stats, 'overall_position'));
                                    $overall_average_overall_position_points = $this->calculate_average(array_column($race_stats, 'overall_position_points'));
                                    $overall_average_age_position = $this->calculate_average(array_column($race_stats, 'age_position'));
                                    $overall_average_age_position_points = $this->calculate_average(array_column($race_stats, 'age_position_points'));
                                    $overall_average_gender_position = $this->calculate_average(array_column($race_stats, 'gender_position'));
                                    $overall_average_gender_position_points = $this->calculate_average(array_column($race_stats, 'gender_position_points'));
                                    $overall_average_hero_rating = $this->calculate_average(array_column($race_stats, 'hero_rating'));
                                    $overall_total_distance_kms = $this->calculate_average(array_column($race_stats, 'total_distance_kms'));
                                    $overall_total_distance_miles = $this->calculate_average(array_column($race_stats, 'total_distance_miles'));
                                    $overall_average_total_race_time = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'overall_time')));
                                    $overall_average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_kms')));
                                    $overall_average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_miles')));
                                    $overall_average_course_rating = $this->calculate_average(array_column($race_stats, 'course_rating'));
                                    $overall_average_obstacle_challenge = $this->calculate_average(array_column($race_stats, 'obstacle_challenge_levels'));
                                    $overall_average_terrain_challenge = $this->calculate_average(array_column($race_stats, 'terrain_challenge_levels'));
                                    $overall_average_weather_factor_challenge = $this->calculate_average(array_column($race_stats, 'main_weather_factor_challenge_levels'));
                                    ?>
                                    <tfoot>
                                    <tr>
                                        <td class="fixed-side"></td>
                                        <td></td>
                                        <td><?php echo $this->ordinal(ceil($overall_average_overall_position)); ?></td>
                                        <td><?php echo ceil($overall_average_overall_position_points); ?>/30</td>
                                        <td><?php echo $this->ordinal(ceil($overall_average_age_position)); ?></td>
                                        <td><?php echo ceil($overall_average_age_position_points); ?>/30</td>
                                        <td><?php echo $this->ordinal(ceil($overall_average_gender_position)); ?></td>
                                        <td><?php echo ceil($overall_average_gender_position_points); ?>/30</td>
                                        <td><?php echo $overall_total_points; ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_total_race_time); ?></td>
                                        <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2);?>%</td>
                                        <td><?php echo number_format($overall_total_distance_kms, 2);?></td>
                                        <td><?php echo number_format($overall_total_distance_miles, 2);?></td>
                                        <td></td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                        <td></td>
                                    </tr>

                                    </tfoot>

                                </table>
                            </div>
                        </div>
                    </div>

                    <?php
                }else{
                    ?>
                    <p class="site-width page-row-pt page-row-pb">No race history found</p>
                    <?php
                }

                ?>
                <div class="one-column page-row-pt bgNavy filters">
                    <div class="site-width">
                        <!-- output filter form here --->
                        <?php $this->get_personal_history_filter_form(0); ?>
                    </div>
                </div>

            </div>
        <?php
        }else{
            ?>
            <p class="site-width page-row-pt page-row-pb"><?php echo $can_access->get_error_message(); ?></p>
            <?php
        }
    }

    /*
     * Personal trophy hunter data output (Top 3 Races)
     */
    public function get_personal_th_race_data_table()
    {

        $can_access = $this->can_access();
        if(!is_wp_error($can_access)) {
            $race_league_points = 'trophy_hunter_running_points';

            $current_user_wocrl_position = 0;
            $current_user_top_3_race_stats = array();

            $vars = array(
                'race_league' => 0,
                'user_id' => $this->get_wocrl_user_id()
            );

            $non_filtered_race_stats = $this->get_leader_board($vars);
            $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);


            // current user must have at least 3 races as a trophy hunter to be in the WOCRL Championship
            $user_ids = array_column($non_filtered_race_stats, 'user_id');
            $user_ids_count_values = array_count_values($user_ids);
            if($user_ids_count_values[$this->get_wocrl_user_id()] >= 3) {

                if ($race_stats) {

                    // group by user id
                    $all_race_stats_formatted = array();
                    foreach ($race_stats as $race_stat) {

                        //calculate trophy hunter points for each race stat
                        $race_stat_id = $race_stat->race_stat_id;
                        $race_stat_event_id = $race_stat->event_id;
                        $race_stat_age_group = $race_stat->age_group;
                        $race_stat_gender = $race_stat->user_gender;

                        $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($this->calculate_overall_position_points($race_stat_id, $race_stat_event_id), $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group), $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender));

                        $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat_event_id);

                        $all_race_stats_formatted[$race_stat->user_id][] = $race_stat;
                    }

                    if ($all_race_stats_formatted) {
                        foreach ($all_race_stats_formatted as $race_stats_formatted_key => $race_stats_formatted) {
                            // sort each users races into highest trophy hunter points, and then only keep top 3
                            array_multisort(
                                array_column($race_stats_formatted, $race_league_points), SORT_DESC);
                            $all_race_stats_formatted[$race_stats_formatted_key] = array_slice($race_stats_formatted, 0, 3);

                            $all_race_stats_formatted[$race_stats_formatted_key]['averages_totals'][$race_league_points] = array_sum(array_column($all_race_stats_formatted[$race_stats_formatted_key], $race_league_points));
                            $all_race_stats_formatted[$race_stats_formatted_key]['averages_totals']['hero_rating'] = $this->calculate_average(array_column($all_race_stats_formatted[$race_stats_formatted_key], 'hero_rating'));

                        }

                        // Should There Still Be One Or More Of The Same Scores, The Fastest Average Ocr Km/Mile Pace, For Those Top Three Races, Will Be Used.
                        if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                            foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                $all_race_stats_formatted[$race_stat_key]['averages_totals']['average_ocr_speed_kms'] = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($race_stat, 'average_ocr_speed_kms'))));
                            }
                            //Should There Still Be One Or More Of The Same Scores, The Highest Average Course Rating, For Those Top Three Races, Will Be Used.
                            if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                                foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                    $all_race_stats_formatted[$race_stat_key]['averages_totals']['course_rating'] = $this->calculate_average(array_column($race_stat, 'course_rating'));
                                }
                                // Should There Still Be One Or More Of The Same Scores, The Highest Average Weather Challenge Rating, For Those Top Three Races, Will Be Used.
                                if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                                    foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                        $all_race_stats_formatted[$race_stat_key]['averages_totals']['weather_rating'] = $this->calculate_average(array_column($race_stat, 'weather_rating'));
                                    }
                                    array_multisort(
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'weather_rating'), SORT_DESC,
                                        $all_race_stats_formatted);
                                } else {
                                    array_multisort(
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                        array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                        $all_race_stats_formatted);
                                }
                            } else {
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    $all_race_stats_formatted);
                            }
                        } else {
                            // sort by highest overall average hero rating and trophy hunter points e.g.
                            // 1ST = RACER 1 = 76 POINTS + 100%
                            // 2ND = RACER 2 = 73 POINTS + 100%
                            // 3RD = RACER 4 = 60 POINTS + 100%
                            // 4TH = RACER 3 = 76 POINTS + 99%
                            // 5TH = RACER 5 = 75 POINTS + 99%
                            // 6TH = RACER 6 = 90 POINTS + 80%
                            array_multisort(
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                $all_race_stats_formatted);
                        }

                        // find stats belonging to current user and get their position in wocrl
                        foreach ($all_race_stats_formatted as $key => $value) {
                            if (in_array($this->get_wocrl_user_id(), array_column($value, 'user_id'))) {
                                $current_user_wocrl_position = ($key + 1); // current position is their key + 1 because array index starts at 0
                                $current_user_top_3_race_stats = $value; // current users top 3 races
                            }
                        }
                    }


                    // unset so it's not included in loop and output as a row
                    unset($current_user_top_3_race_stats['averages_totals']);


                    ?>

                    <div class="wocrl_league_output">
                        <div class="one-column bgWhite page-row-pt league_tables">
                        <?php $this->get_league_table_scroll_buttons(); ?>
                        <div id="table-scroll" class="table-scroll">
                            <div class="table-wrap">
                                <table class="main-table">

                                    <thead>
                                    <tr>
                                        <th class="fixed-side">#</th>
                                        <th class="fixed-side">Race</th>
                                        <th>Trophy Hunter<br/>Points</th>
                                        <th>Total<br/>Race Time</th>
                                        <th>Hero<br/>Rating</th>
                                        <th>Average OCR<br/>Pace (KMS)</th>
                                        <th>Average OCR<br/>Pace (MILES)</th>
                                        <th>Course<br/>Rating</th>
                                        <th>Obstacle<br/>Challenge</th>
                                        <th>Terrain<br/>Challenge</th>
                                        <th>Weather<br/>Challenge</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    foreach ($current_user_top_3_race_stats as $arsfKey => $races) {

                                        // get position in this race
                                        $position = $this->calculate_overall_position($races->race_stat_id, $races->event_id);
                                        $trophy_hunter_points = $races->{$race_league_points};

                                        ?>

                                        <tr>
                                            <td class="fixed-side"><?php echo $this->ordinal($position); ?></td>
                                            <td class="fixed-side"><?php echo $races->event_title; ?></td>
                                            <td><?php echo $trophy_hunter_points; ?></td>
                                            <td><?php echo $races->overall_time; ?></td>
                                            <td><?php echo number_format(ceil(($races->hero_rating * 100)), 2); ?>%</td>
                                            <td><?php echo $races->average_ocr_speed_kms; ?></td>
                                            <td><?php echo $races->average_ocr_speed_miles; ?></td>
                                            <td><?php echo ceil($races->course_rating); ?>/40</td>
                                            <td><?php echo ceil($races->obstacle_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($races->terrain_challenge_levels); ?>/10</td>
                                            <td><?php echo ceil($races->main_weather_factor_challenge_levels); ?>/9</td>
                                        </tr>

                                        <?php
                                    }
                                    ?>
                                    </tbody>

                                    <?php

                                    /* Overall averages from all races  */
                                    $overall_total_points = array_sum(array_column($current_user_top_3_race_stats, $race_league_points));
                                    $overall_average_overall_time = $this->calculate_average(array_map('strtotime', array_column($current_user_top_3_race_stats, 'overall_time')));
                                    $overall_average_hero_rating = $this->calculate_average(array_column($current_user_top_3_race_stats, 'hero_rating'));
                                    $overall_average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($current_user_top_3_race_stats, 'average_ocr_speed_kms')));
                                    $overall_average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($current_user_top_3_race_stats, 'average_ocr_speed_miles')));
                                    $overall_average_course_rating = $this->calculate_average(array_column($current_user_top_3_race_stats, 'course_rating'));
                                    $overall_average_obstacle_challenge = $this->calculate_average(array_column($current_user_top_3_race_stats, 'obstacle_challenge_levels'));
                                    $overall_average_terrain_challenge = $this->calculate_average(array_column($current_user_top_3_race_stats, 'terrain_challenge_levels'));
                                    $overall_average_weather_factor_challenge = $this->calculate_average(array_column($current_user_top_3_race_stats, 'main_weather_factor_challenge_levels'));
                                    ?>
                                    <tfoot>
                                    <tr>
                                        <td><?php echo $this->ordinal($current_user_wocrl_position); ?></td>
                                        <td></td>
                                        <td><?php echo $overall_total_points; ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_overall_time); ?></td>
                                        <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2); ?>%
                                        </td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                        <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                        <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                    </tr>
                                    </tfoot>

                                </table>
                            </div>
                        </div>
                    </div>

                    <?php
                } else {
                    ?>
                    <p class="site-width page-row-pt page-row-pb">No races found</p>
                    <?php
                }

                ?>
                <div class="one-column page-row-pt page-row-pb bgNavy filters">
                    <div class="site-width">
                        <!-- output filter form here --->
                        <?php $this->get_personal_league_table_filter_form($current_user_top_3_race_stats); ?>
                    </div>
                </div>
                <?php
            }else{
                ?>
                <p class="site-width page-row-pt page-row-pb">You Must Enter the Race Stats From A Minimum Of 3 Events As A Trophy Hunter To Qualify</p>
                <?php
            }
        }else{
              ?>
              <p class="site-width page-row-pt page-row-pb"><?php echo $can_access->get_error_message(); ?></p>
              <?php
        }
    }

    /*
     * Personal fun runner data output
     */
    public function get_personal_fr_race_data_table(){

        $can_access = $this->can_access();
        if(!is_wp_error($can_access)) {
            // get all of current users fun runner race stats from current year

            // build query args for prepare
            $vars = array(
                'race_league' => 1,
                'user_id' => $this->get_wocrl_user_id(),
                'year' => date('Y')
            );

            $non_filtered_race_stats = $this->get_leader_board($vars);
            $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);

            $monthly_race_stats = array();

            ?>
            <div class="wocrl_league_output">
                <?php
                if($race_stats){

                    // group into months
                    foreach($race_stats as $race_stat) {

                        $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat->event_id);

                        $event_month = date('m', strtotime($race_stat->event_start_date));
                        $monthly_race_stats[$event_month][] = $race_stat;

                    }

                    ?>

                    <div class="one-column bgWhite page-row-pt league_tables">
                        <?php $this->get_league_table_scroll_buttons(); ?>
                        <div id="table-scroll" class="table-scroll personal-fr-data-table">
                            <div class="table-wrap">
                                <table class="main-table">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Total Race Numbers</th>
                                        <th>Total Distance (KMs)</th>
                                        <th>Total Distance (Miles)</th>
                                        <th>Average Hero Rating</th>
                                        <th>Fastest Average OCR KM</th>
                                        <th>Fastest Average OCR Mile</th>
                                        <th>Slowest Average OCR KM</th>
                                        <th>Slowest Average OCR Mile</th>
                                        <th>Average Course Rating</th>
                                        <th>Average Weather Rating</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <th>Year to Date (<?php echo date('Y'); ?>)</th>
                                        <td><?php echo (!empty($race_stats)) ? count($race_stats) : 0; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? number_format(array_sum(array_column($race_stats, 'total_distance_kms')), 2) : '0.00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? number_format(array_sum(array_column($race_stats, 'total_distance_miles')), 2) : '0.00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? number_format(ceil($this->calculate_average(array_column($race_stats, 'hero_rating')) * 100), 2) : '0.00'; ?>%</td>
                                        <td><?php echo (!empty($race_stats)) ? date('H:i:s', min(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_kms')))) : '00:00:00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? date('H:i:s', min(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_miles')))): '00:00:00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? date('H:i:s', max(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_kms')))): '00:00:00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? date('H:i:s', max(array_map('strtotime', array_column($race_stats, 'average_ocr_speed_miles')))): '00:00:00'; ?></td>
                                        <td><?php echo (!empty($race_stats)) ? number_format($this->calculate_average(array_column($race_stats, 'course_rating'))) : 0; ?>/40</td>
                                        <td><?php echo (!empty($race_stats)) ? number_format($this->calculate_average(array_column($race_stats, 'weather_rating'))) : 0; ?>/10</td>
                                    </tr>
                                    </tbody>
                                </table>

                                <table class="main-table">

                                    <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Race Numbers</th>
                                        <th>Total Distance (KMs)</th>
                                        <th>Total Distance (Miles)</th>
                                        <th>Average Hero Rating</th>
                                        <th>Fastest Average OCR KM</th>
                                        <th>Fastest Average OCR Mile</th>
                                        <th>Slowest Average OCR KM</th>
                                        <th>Slowest Average OCR Mile</th>
                                        <th>Average Course Rating</th>
                                        <th>Average Weather Rating</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    for($month=1; $month<=12; ++$month){
                                        $month_name = date('F', mktime(0, 0, 0, $month, 10)); // March

                                        if($month < 10){
                                            $month = '0'.$month;
                                        }

                                        $monthly_race_stat = $monthly_race_stats[$month];

                                        ?>
                                        <tr>
                                            <th><?php echo $month_name; ?></th>
                                            <td><?php echo (!empty($monthly_race_stat)) ? count($monthly_race_stat) : 0; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? number_format(array_sum(array_column($monthly_race_stat, 'total_distance_kms')), 2) : '0.00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? number_format(array_sum(array_column($monthly_race_stat, 'total_distance_miles')), 2) : '0.00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? number_format(ceil($this->calculate_average(array_column($monthly_race_stat, 'hero_rating')) * 100), 2) : '0.00'; ?>%</td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? date('H:i:s', min(array_map('strtotime', array_column($monthly_race_stat, 'average_ocr_speed_kms')))) : '00:00:00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? date('H:i:s', min(array_map('strtotime', array_column($monthly_race_stat, 'average_ocr_speed_miles')))): '00:00:00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? date('H:i:s', max(array_map('strtotime', array_column($monthly_race_stat, 'average_ocr_speed_kms')))): '00:00:00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? date('H:i:s', max(array_map('strtotime', array_column($monthly_race_stat, 'average_ocr_speed_miles')))): '00:00:00'; ?></td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? number_format($this->calculate_average(array_column($monthly_race_stat, 'course_rating'))) : 0; ?>/40</td>
                                            <td><?php echo (!empty($monthly_race_stat)) ? number_format($this->calculate_average(array_column($monthly_race_stat, 'weather_rating'))) : 0; ?>/10</td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php
                }else{
                    ?>
                    <p class="site-width page-row-pt page-row-pb">No races found</p>
                    <?php
                }
                ?>
            </div>
            <?php
        }else{
            ?>
            <p class="site-width page-row-pt page-row-pb"><?php echo $can_access->get_error_message(); ?></p>
            <?php
        }
    }

    /*
     * WOCRL Championship data output
     */
    public function get_wocrl_championship_data_table()
    {
        $race_league_points = 'trophy_hunter_running_points';

        $vars = array(
            'race_league' => 0
        );

        $non_filtered_race_stats = $this->get_leader_board($vars);
        $race_stats = $this->get_league_table_filter_query($non_filtered_race_stats);


        ?>
        <div class="wocrl_league_output">
            <?php
                if($race_stats){

                // group by user id
                $all_race_stats_formatted = array();
                foreach($race_stats as $race_stat){

                    //calculate trophy hunter points for each race stat
                    $race_stat_id = $race_stat->race_stat_id;
                    $race_stat_event_id = $race_stat->event_id;
                    $race_stat_age_group = $race_stat->age_group;
                    $race_stat_gender = $race_stat->user_gender;

                    $race_stat->{$race_league_points} = $this->calculate_trophy_hunter_running_points($this->calculate_overall_position_points($race_stat_id, $race_stat_event_id), $this->calculate_age_group_position_points($race_stat_id, $race_stat_event_id, $race_stat_age_group), $this->calculate_gender_position_points($race_stat_id, $race_stat_event_id, $race_stat_gender));

                    $race_stat->course_rating = $this->calculate_average_event_course_rating($race_stat_event_id);

                    $all_race_stats_formatted[$race_stat->user_id][] = $race_stat;
                }

                if($all_race_stats_formatted) {
                    foreach ($all_race_stats_formatted as $race_stats_formatted_key => $race_stats_formatted) {

                        if(count($race_stats_formatted) >= 3) {
                            // sort each users races into highest trophy hunter points, and then only keep top 3
                            array_multisort(
                                array_column($race_stats_formatted, $race_league_points), SORT_DESC);
                            $all_race_stats_formatted[$race_stats_formatted_key] = array_slice($race_stats_formatted, 0, 3);

                            $all_race_stats_formatted[$race_stats_formatted_key]['averages_totals'][$race_league_points] = array_sum(array_column($all_race_stats_formatted[$race_stats_formatted_key], $race_league_points));
                            $all_race_stats_formatted[$race_stats_formatted_key]['averages_totals']['hero_rating'] = $this->calculate_average(array_column($all_race_stats_formatted[$race_stats_formatted_key], 'hero_rating'));
                        }else{
                            unset($all_race_stats_formatted[$race_stats_formatted_key]);
                        }

                    }

                    // Should There Still Be One Or More Of The Same Scores, The Fastest Average Ocr Km/Mile Pace, For Those Top Three Races, Will Be Used.
                    if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {

                        foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                            $all_race_stats_formatted[$race_stat_key]['averages_totals']['average_ocr_speed_kms'] = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($race_stat, 'average_ocr_speed_kms'))));
                        }
                        //Should There Still Be One Or More Of The Same Scores, The Highest Average Course Rating, For Those Top Three Races, Will Be Used.
                        if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                            foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                $all_race_stats_formatted[$race_stat_key]['averages_totals']['course_rating'] = $this->calculate_average(array_column($race_stat, 'course_rating'));
                            }
                            // Should There Still Be One Or More Of The Same Scores, The Highest Average Weather Challenge Rating, For Those Top Three Races, Will Be Used.
                            if (count(array_column($all_race_stats_formatted, 'averages_totals')) != count(array_unique(array_column($all_race_stats_formatted, 'averages_totals'), SORT_REGULAR))) {
                                foreach ($all_race_stats_formatted as $race_stat_key => $race_stat) {
                                    $all_race_stats_formatted[$race_stat_key]['averages_totals']['weather_rating'] = $this->calculate_average(array_column($race_stat, 'weather_rating'));
                                }
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'weather_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            } else {
                                array_multisort(
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                    array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'course_rating'), SORT_DESC,
                                    $all_race_stats_formatted);
                            }
                        } else {
                            array_multisort(
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                                array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'average_ocr_speed_kms'), SORT_ASC,
                                $all_race_stats_formatted);
                        }
                    } else {
                        // sort by highest overall average hero rating and trophy hunter points e.g.
                        // 1ST = RACER 1 = 76 POINTS + 100%
                        // 2ND = RACER 2 = 73 POINTS + 100%
                        // 3RD = RACER 4 = 60 POINTS + 100%
                        // 4TH = RACER 3 = 76 POINTS + 99%
                        // 5TH = RACER 5 = 75 POINTS + 99%
                        // 6TH = RACER 6 = 90 POINTS + 80%
                        array_multisort(
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), 'hero_rating'), SORT_DESC,
                            array_column(array_column($all_race_stats_formatted, 'averages_totals'), $race_league_points), SORT_DESC,
                            $all_race_stats_formatted);
                    }
                }


                ?>

                <div class="one-column bgWhite page-row-pt league_tables">
                    <?php $this->get_league_table_scroll_buttons(); ?>
                    <div id="table-scroll" class="table-scroll">
                        <div class="table-wrap">
                            <table class="main-table">

                                <thead>
                                <tr>
                                    <th class="fixed-side">#</th>
                                    <th class="fixed-side">Racer</th>
                                    <th>Trophy Hunter<br/>Points</th>
                                    <th>Hero<br/>Rating</th>
                                    <th>Average OCR<br/>Pace (KMS)</th>
                                    <th>Average OCR<br/>Pace (MILES)</th>
                                    <th>Course<br/>Rating</th>
                                    <th>Obstacle<br/>Challenge</th>
                                    <th>Terrain<br/>Challenge</th>
                                    <th>Weather<br/>Challenge</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                foreach ($all_race_stats_formatted as $arsfKey => $races) {

                                    $averages = $races['averages_totals'];

                                    $user_id = $races[0]->user_id;
                                    $user_first_name = $races[0]->user_first_name;
                                    $user_last_name = $races[0]->user_last_name;

                                    $position = ($arsfKey + 1);
                                    $position_points = $averages[$race_league_points];
                                    $overall_average_hero_rating = $averages['hero_rating'];

                                    $overall_average_ocr_speed_miles = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($races, 'average_ocr_speed_miles'))));
                                    $all_race_stats_formatted[$arsfKey]['averages_totals']['average_ocr_speed_miles'] = $overall_average_ocr_speed_miles; // add to averages array so we can work out overall average for the league below

                                    if(isset($averages['average_ocr_speed_kms'])){
                                        $overall_average_ocr_speed_kms = $averages['average_ocr_speed_kms'];
                                    }else{
                                        $overall_average_ocr_speed_kms = date('H:i:s', $this->calculate_average(array_map('strtotime', array_column($races, 'average_ocr_speed_kms'))));
                                        $all_race_stats_formatted[$arsfKey]['averages_totals']['average_ocr_speed_kms'] = $overall_average_ocr_speed_kms; // add to averages array so we can work out overall average for the league below
                                    }


                                    if(isset($averages['course_rating'])){
                                        $overall_average_course_rating = $averages['course_rating'];
                                    }else{
                                        $overall_average_course_rating = $this->calculate_average(array_column($races, 'course_rating'));
                                        $all_race_stats_formatted[$arsfKey]['averages_totals']['course_rating'] = $overall_average_course_rating; // add to averages array so we can work out overall average for the league below
                                    }

                                    $overall_average_obstacle_challenge = $this->calculate_average(array_column($races, 'obstacle_challenge_levels'));
                                    $all_race_stats_formatted[$arsfKey]['averages_totals']['obstacle_challenge_levels'] = $overall_average_obstacle_challenge; // add to averages array so we can work out overall average for the league below

                                    $overall_average_terrain_challenge = $this->calculate_average(array_column($races, 'terrain_challenge_levels'));
                                    $all_race_stats_formatted[$arsfKey]['averages_totals']['terrain_challenge_levels'] = $overall_average_terrain_challenge; // add to averages array so we can work out overall average for the league below

                                    $overall_average_weather_factor_challenge = $this->calculate_average(array_column($races, 'main_weather_factor_challenge_levels'));
                                    $all_race_stats_formatted[$arsfKey]['averages_totals']['main_weather_factor_challenge_levels'] = $overall_average_weather_factor_challenge; // add to averages array so we can work out overall average for the league below

                                    ?>

                                    <tr data-user-id="<?php echo $user_id; ?>">
                                        <td class="fixed-side"><?php echo $this->ordinal($position); ?></td>
                                        <td class="fixed-side"><?php echo $user_first_name[0]; ?>.<?php echo $user_last_name; ?></td>
                                        <td><?php echo $position_points; ?></td>
                                        <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2); ?>%</td>
                                        <td><?php echo $overall_average_ocr_speed_kms; ?></td>
                                        <td><?php echo $overall_average_ocr_speed_miles; ?></td>
                                        <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                        <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                        <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                    </tr>

                                    <?php
                                }
                                ?>
                                </tbody>

                                <?php

                                /* Overall averages from all races  */

                                $all_averages_totals = array_column($all_race_stats_formatted, 'averages_totals');

                                $overall_total_points = array_sum(array_column($all_averages_totals, $race_league_points));
                                $overall_average_hero_rating = $this->calculate_average(array_column($all_averages_totals, 'hero_rating'));
                                $overall_average_ocr_speed_kms = $this->calculate_average(array_map('strtotime', array_column($all_averages_totals, 'average_ocr_speed_kms')));
                                $overall_average_ocr_speed_miles = $this->calculate_average(array_map('strtotime', array_column($all_averages_totals, 'average_ocr_speed_miles')));
                                $overall_average_course_rating = $this->calculate_average(array_column($all_averages_totals, 'course_rating'));
                                $overall_average_obstacle_challenge = $this->calculate_average(array_column($all_averages_totals, 'obstacle_challenge_levels'));
                                $overall_average_terrain_challenge = $this->calculate_average(array_column($all_averages_totals, 'terrain_challenge_levels'));
                                $overall_average_weather_factor_challenge = $this->calculate_average(array_column($all_averages_totals, 'main_weather_factor_challenge_levels'));
                                ?>
                                <tfoot>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td><?php echo $overall_total_points; ?></td>
                                    <td><?php echo number_format(ceil(($overall_average_hero_rating * 100)), 2); ?>%</td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_kms); ?></td>
                                    <td><?php echo date('H:i:s', $overall_average_ocr_speed_miles); ?></td>
                                    <td><?php echo ceil($overall_average_course_rating); ?>/40</td>
                                    <td><?php echo ceil($overall_average_obstacle_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_terrain_challenge); ?>/10</td>
                                    <td><?php echo ceil($overall_average_weather_factor_challenge); ?>/9</td>
                                </tr>

                                </tfoot>

                            </table>
                        </div>
                    </div>
                </div>

                <?php
            } else {
                ?>
                <p class="site-width page-row-pt page-row-pb">No races found</p>
                <?php
            }

            ?>
            <div class="one-column page-row-pt page-row-pb bgNavy filters">
                <div class="site-width">
                    <!-- output filter form here --->
                    <?php $this->get_championship_table_filter_form(); ?>
                </div>
            </div>

        </div>

        <?php
    }

    /*
     * Get general league table filter form
     */
    public function get_league_table_filter_form($race_league_id=0){
        $can_access = $this->can_access();
        //if(!is_wp_error($can_access)) {

            $vars = array();
            // make query dynamic depending on the league so we only output filter options that will return results
            switch ($race_league_id) {
                case 2:
                    $group_by = 'event_id';
                    break;
                case 3:
                    $group_by = 'community_id';
                    break;
                default:
                    $group_by = 'user_id';
                    $vars = array(
                            'race_league' => $race_league_id
                    );
                    break;
            }

            $race_stats = $this->get_leader_board($vars);

            if($race_stats) {

                $all_number_of_races_cats = $this->get_number_of_races_categories();
                $number_of_races_cats = array();
                // group stats together and count how many races there are
                $grouped_stats = array();
                foreach ($race_stats as $rs) {
                    if (!isset($grouped_stats[$rs->{$group_by}])) {
                        $grouped_stats[$rs->{$group_by}] = 1;
                    } else {
                        $grouped_stats[$rs->{$group_by}] = $grouped_stats[$rs->{$group_by}] + 1;
                    }
                }
                // get the number of races category for each
                foreach ($grouped_stats as $gs_key => $gs) {
                    $number_of_races_cat = $this->get_number_of_races_category($gs);
                    if (!in_array($number_of_races_cat, $number_of_races_cats)) {
                        $number_of_races_cats[] = $number_of_races_cat;
                    }
                }
                // sort so smallest groups are first
                $key = array_column($number_of_races_cats, 'key');
                array_multisort($key, SORT_ASC, $number_of_races_cats);


                // get all event ids from race stats
                $event_ids = array_unique(array_column($race_stats, 'event_id'));

                // get all community ids from race stats
                $community_ids = array_unique(array_column($race_stats, 'community_id'));

                // get all distance categories from race stats
                $all_distance_categories = $this->get_distance_categories();
                $distance_categories = array_unique(array_column($race_stats, 'distance_category'));

                // get all user countries from race stats
                $all_countries = $this->get_countries();
                $user_countries = array_unique(array_column($race_stats, 'user_country'));

                // get all user genders from race stats
                $all_genders = array('male' => 'Male', 'female' => 'Female');
                $user_genders = array_unique(array_column($race_stats, 'user_gender'));

                // get all age groups from race stats
                $all_age_groups = $this->get_age_groups();
                $age_groups = array_unique(array_column($race_stats, 'age_group'));

                // get all event countries from race stats
                $event_countries = array_unique(array_column($race_stats, 'event_country'));

                // get all event years and months from race stats
                $all_years = array_combine(range(date("Y"), 1900), range(date("Y"), 1900));
                $all_months = array_reduce(range(1, 12), function ($rslt, $m) {
                    $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
                    return $rslt;
                });

                $event_days = array_unique(array_column($race_stats, 'event_start_date'));
                $event_years = array();
                $event_months = array();
                if ($event_days) {
                    foreach ($event_days as $event_day) {
                        $event_year = date('Y', strtotime($event_day));
                        $event_month = date('m', strtotime($event_day));

                        if (!in_array($event_year, $event_years)) {
                            $event_years[] = $event_year;
                        }
                        if (!in_array($event_month, $event_months)) {
                            $event_months[] = $event_month;
                        }
                    }
                }

                // doing the array_map 'strtolower' so we dont get options outputting twice in caps and non-caps (bug from import)
                $weather_factors = array_unique(array_map("strtolower", array_column($race_stats, 'main_weather_factor')));

                // only show league filters if race director or community leagues
                if (in_array($race_league_id, array(2, 3))) {
                    $race_leagues = array_unique(array_column($race_stats, 'race_league'));
                }

                ?>

                <!-- Search, Filter & 'Show Me' action buttons -->
                <div class="action-buttons">
                    <div class="two-thirds">
                        <div class="columns-2">
                            <div class="item">
                                <button <?php /* 'show me' functionality only for fr or th */
                                echo (!in_array($race_league_id, array(1, 0))) ? 'disabled' : ''; ?>
                                        class="show_me" data-current-user-id="<?php echo $this->get_wocrl_user_id(); ?>">Show
                                    Me
                                </button>
                            </div>
                            <div class="item">
                                <button class="filter_league_table">Filters</button>
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
                    <div class="one-third">
                        <div class="item">
                            <button class="search_league_table"><i class="fa fa-search" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>


                <!--- Filter Form --->
                <form id="raceLeagueFilter" method="get">
                    <?php
                    if ($event_ids) {
                        ?>
                        <select name="filter_event_id">
                            <option value="">Race Name</option>
                            <?php
                            foreach ($event_ids as $event_id) {
                                $event_id_key = array_search($event_id, array_column($race_stats, 'event_id'));
                                $event_title = $race_stats[$event_id_key]->event_title;
                                ?>
                                <option value="<?php echo $event_id; ?>" <?php echo (isset($_GET['filter_event_id']) && wp_strip_all_tags($_GET['filter_event_id']) == $event_id) ? 'selected' : ''; ?>><?php echo $event_title; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php

                    if ($all_number_of_races_cats) {
                        ?>
                        <select name="filter_number_of_races">
                            <option value="">Number of Races</option>
                            <?php
                            foreach ($all_number_of_races_cats as $nrcs_key => $nrcs) {
                                ?>
                                <option value="<?php echo $nrcs_key; ?>" <?php echo (!in_array($nrcs_key, array_column($number_of_races_cats, 'key'))) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_number_of_races']) && (wp_strip_all_tags($_GET['filter_number_of_races']) != '' && wp_strip_all_tags($_GET['filter_number_of_races']) == $nrcs_key)) ? 'selected' : ''; ?>><?php echo $nrcs['label']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($community_ids) {
                        ?>
                        <select name="filter_community_id">
                            <option value="">Community</option>
                            <?php
                            foreach ($community_ids as $community_id) {
                                $community_id_key = array_search($community_id, array_column($race_stats, 'community_id'));
                                $community_name = $race_stats[$community_id_key]->community_name;
                                ?>
                                <option value="<?php echo $community_id; ?>" <?php echo (isset($_GET['filter_community_id']) && wp_strip_all_tags($_GET['filter_community_id']) == $community_id) ? 'selected' : ''; ?>><?php echo $community_name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_countries) {
                        ?>
                        <select name="filter_user_country">
                            <option value="">Country of Origin</option>
                            <?php
                            foreach ($all_countries as $user_country_code => $user_country) {
                                ?>
                                <option value="<?php echo $user_country_code; ?>" <?php echo (!in_array($user_country_code, $user_countries)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_user_country']) && wp_strip_all_tags($_GET['filter_user_country']) == $user_country_code) ? 'selected' : ''; ?>><?php echo $user_country; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_countries) {
                        ?>
                        <select name="filter_event_country">
                            <option value="">Country of Race</option>
                            <?php
                            foreach ($all_countries as $event_country_code => $event_country) {
                                ?>
                                <option value="<?php echo $event_country_code; ?>" <?php echo (!in_array($event_country_code, $event_countries)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_country']) && wp_strip_all_tags($_GET['filter_event_country']) == $event_country_code) ? 'selected' : ''; ?>><?php echo $event_country; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_age_groups) {
                        ?>
                        <select name="filter_age_group">
                            <option value="">Age Group</option>
                            <?php
                            foreach ($all_age_groups as $age_group_id => $age_group) {
                                ?>
                                <option value="<?php echo $age_group_id; ?>" <?php echo (!in_array($age_group_id, $age_groups)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_age_group']) && wp_strip_all_tags($_GET['filter_age_group']) == "{$age_group_id}") ? 'selected' : ''; ?>><?php echo $age_group['name']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_genders) {
                        ?>
                        <select name="filter_gender">
                            <option value="">Gender</option>
                            <?php
                            foreach ($all_genders as $gender_key => $user_gender) {
                                ?>
                                <option value="<?php echo $gender_key; ?>" <?php echo (!in_array($gender_key, $user_genders)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_gender']) && wp_strip_all_tags($_GET['filter_gender']) == $gender_key) ? 'selected' : ''; ?>><?php echo ucfirst($user_gender); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_distance_categories) {
                        ?>
                        <select name="filter_distance_category">
                            <option value="">Distance Category</option>
                            <?php
                            foreach ($all_distance_categories as $distance_category_id => $distance_category) {
                                ?>
                                <option value="<?php echo $distance_category_id; ?>" <?php echo (!in_array($distance_category_id, $distance_categories)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_distance_category']) && wp_strip_all_tags($_GET['filter_distance_category']) == "{$distance_category_id}") ? 'selected' : ''; ?>><?php echo $distance_category['name']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_years) {
                        ?>
                        <select name="filter_event_year">
                            <option value="">Year of Race</option>
                            <?php
                            foreach ($all_years as $event_year) {
                                ?>
                                <option value="<?php echo $event_year; ?>" <?php echo (!in_array($event_year, $event_years)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_year']) && wp_strip_all_tags($_GET['filter_event_year']) == $event_year) ? 'selected' : ''; ?>><?php echo $event_year; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>
                    <?php
                    if ($all_months) {
                        ?>
                        <select name="filter_event_month">
                            <option value="">Month of Race</option>
                            <?php
                            foreach ($all_months as $event_month => $month_name) {
                                ?>
                                <option value="<?php echo ($event_month < 10) ? '0' . $event_month : $event_month; ?>" <?php echo (!in_array($event_month, $event_months)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_month']) && wp_strip_all_tags($_GET['filter_event_month']) == $event_month) ? 'selected' : ''; ?>><?php echo $month_name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if (!empty($race_leagues)) {
                        ?>
                        <select name="filter_race_league">
                            <option value="">Trophy Hunter or Fun Runner</option>
                            <?php
                            foreach ($race_leagues as $race_league) {
                                ?>
                                <option value="<?php echo $race_league; ?>" <?php echo (isset($_GET['filter_race_league']) && wp_strip_all_tags($_GET['filter_race_league']) == $race_league) ? 'selected' : ''; ?>><?php echo $this->get_league($race_league); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if (!empty($weather_factors)) {
                        ?>
                        <select name="filter_main_weather_factor">
                            <option value="">Main Weather Factor</option>
                            <?php
                            foreach ($weather_factors as $weather_factor) {
                                ?>
                                <option value="<?php echo $weather_factor; ?>" <?php echo (isset($_GET['filter_main_weather_factor']) && wp_strip_all_tags($_GET['filter_main_weather_factor']) == $weather_factor) ? 'selected' : ''; ?>><?php echo ucwords($weather_factor); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>

                    <?php
                    // hidden fields for the search so users can filter search results
                    if (isset($_GET['filter_racer_name']) && wp_strip_all_tags($_GET['filter_racer_name']) != '') {
                        ?>
                        <input type="hidden" name="filter_racer_name" value="<?php echo wp_strip_all_tags($_GET['filter_racer_name']); ?>">
                        <?php
                    }
                    ?>

                    <p>
                        <button type="submit">Filter</button>
                    </p>
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="button button-secondary clear_filters">Clear
                        Filters</a>
                </form>
                <!--- End of Filter Form --->

                <!--- Search Form --->
                <form id="raceLeagueSearch" method="get">
                    <?php
                    // hidden fields for all the filters so users can search filtered results
                    foreach ($_GET as $key => $value) {
                        if ($key == 'filter_racer_name') {
                            continue;
                        }
                        ?>
                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo wp_strip_all_tags($value); ?>">
                        <?php
                    }
                    ?>
                    <input type="text" name="filter_racer_name"
                           value="<?php echo (isset($_GET['filter_racer_name']) && wp_strip_all_tags($_GET['filter_racer_name']) != '') ? wp_strip_all_tags($_GET['filter_racer_name']) : ''; ?>"
                           placeholder="Search (name)">
                    <button type="submit">Search</button>
                </form>
                <!--- End of Search Form --->

                <?php
            }
        //}

    }

    /*
     * Get personal data table filter form
     */
    public function get_personal_league_table_filter_form($race_stats){

        $can_access = $this->can_access();
        //if(!is_wp_error($can_access)) {

            // get all user genders from race stats
            $all_genders = array('male' => 'Male', 'female' => 'Female');
            $user_genders = array_unique(array_column($race_stats, 'user_gender'));

            $all_age_groups = $this->get_age_groups();
            $age_groups = array_unique(array_column($race_stats, 'age_group'));
            sort($age_groups);

            // get all distance categories from race stats
            $all_distance_categories = $this->get_distance_categories();
            $distance_categories = array_unique(array_column($race_stats, 'distance_category'));
            sort($distance_categories);

            ?>

            <!-- Search, Filter & 'Show Me' action buttons -->
            <div class="action-buttons">
                <div class="columns-1">
                    <div class="item">
                        <button class="filter_league_table">Filters</button>
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="clear"></div>
            </div>


            <!--- Filter Form --->
            <form id="raceLeagueFilter" method="get">
                <?php
                if ($all_genders) {
                    ?>
                    <select name="filter_gender">
                        <option value="">Gender</option>
                        <?php
                        foreach ($all_genders as $gender_key => $user_gender) {
                            ?>
                            <option value="<?php echo $gender_key; ?>" <?php echo (!in_array($gender_key, $user_genders)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_gender']) && wp_strip_all_tags($_GET['filter_gender']) == $gender_key) ? 'selected' : ''; ?>><?php echo ucfirst($user_gender); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                if ($all_age_groups) {
                    ?>
                    <select name="filter_age_group">
                        <option value="">Age Group</option>
                        <?php
                        foreach ($all_age_groups as $age_group_id => $age_group) {
                            ?>
                            <option value="<?php echo $age_group_id; ?>" <?php echo (!in_array($age_group_id, $age_groups)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_age_group']) && wp_strip_all_tags($_GET['filter_age_group']) == "{$age_group_id}") ? 'selected' : ''; ?>><?php echo $age_group['name']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                if ($all_distance_categories) {
                    ?>
                    <select name="filter_distance_category">
                        <option value="">Distance Category</option>
                        <?php
                        foreach ($all_distance_categories as $distance_category_id => $distance_category) {
                            ?>
                            <option value="<?php echo $distance_category_id; ?>" <?php echo (!in_array($distance_category_id, $distance_categories)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_distance_category']) && wp_strip_all_tags($_GET['filter_distance_category']) == $distance_category_id) ? 'selected' : ''; ?>><?php echo $distance_category['name']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>

                <p>
                    <button type="submit">Filter</button>
                </p>
                <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="button button-secondary">Clear Filters</a>
            </form>
            <!--- End of Filter Form --->

            <?php
        //}

    }

    /*
     * Get personal data table filter form
     */
    public function get_personal_history_filter_form($race_league_id=0){

        $can_access = $this->can_access();
        //if(!is_wp_error($can_access)) {
            $vars = array(
                'race_league' => $race_league_id,
                'user_id' => $this->get_wocrl_user_id()
            );
            $race_stats = $this->get_leader_board($vars);

            if($race_stats) {

                // get all user genders from race stats
                $all_genders = array('male' => 'Male', 'female' => 'Female');
                $user_genders = array_unique(array_column($race_stats, 'user_gender'));

                $all_age_groups = $this->get_age_groups();
                $age_groups = array_unique(array_column($race_stats, 'age_group'));
                sort($age_groups);

                // get all event years and months from race stats
                $all_years = array_combine(range(date("Y"), 1900), range(date("Y"), 1900));
                $all_months = array_reduce(range(1, 12), function ($rslt, $m) {
                    $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
                    return $rslt;
                });

                $event_days = array_unique(array_column($race_stats, 'event_start_date'));
                $event_years = array();
                $event_months = array();
                if ($event_days) {
                    foreach ($event_days as $event_day) {
                        $event_year = date('Y', strtotime($event_day));
                        $event_month = date('m', strtotime($event_day));

                        if (!in_array($event_year, $event_years)) {
                            $event_years[] = $event_year;
                        }
                        if (!in_array($event_month, $event_months)) {
                            $event_months[] = $event_month;
                        }
                    }
                }
                // sort into ascending/descending order so that months and years appear in the right order
                rsort($event_years);
                sort($event_months);


                // doing the array_map 'strtolower' so we dont get options outputting twice in caps and non-caps (bug from import)
                $weather_factors = array_unique(array_map("strtolower", array_column($race_stats, 'main_weather_factor')));

                // get all distance categories from race stats
                $all_distance_categories = $this->get_distance_categories();
                $distance_categories = array_unique(array_column($race_stats, 'distance_category'));
                sort($distance_categories);

                ?>

                <!-- Search, Filter & 'Show Me' action buttons -->
                <div class="action-buttons">
                    <div class="columns-1">
                        <div class="item">
                            <button class="filter_league_table">Filters</button>
                        </div>
                        <div class="clear"></div>
                    </div>
                    <div class="clear"></div>
                </div>


                <!--- Filter Form --->
                <form id="raceLeagueFilter" method="get">
                    <?php
                    if ($all_genders) {
                        ?>
                        <select name="filter_gender">
                            <option value="">Gender</option>
                            <?php
                            foreach ($all_genders as $gender_key => $user_gender) {
                                ?>
                                <option value="<?php echo $gender_key; ?>" <?php echo (!in_array($gender_key, $user_genders)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_gender']) && wp_strip_all_tags($_GET['filter_gender']) == $gender_key) ? 'selected' : ''; ?>><?php echo ucfirst($user_gender); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if ($all_age_groups) {
                        ?>
                        <select name="filter_age_group">
                            <option value="">Age Group</option>
                            <?php
                            foreach ($all_age_groups as $age_group_id => $age_group) {
                                ?>
                                <option value="<?php echo $age_group_id; ?>" <?php echo (!in_array($age_group_id, $age_groups)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_age_group']) && wp_strip_all_tags($_GET['filter_age_group']) == "{$age_group_id}") ? 'selected' : ''; ?>><?php echo $age_group['name']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if ($all_years) {
                        ?>
                        <select name="filter_event_year">
                            <option value="">Year of Race</option>
                            <?php
                            foreach ($all_years as $event_year) {
                                ?>
                                <option value="<?php echo $event_year; ?>" <?php echo (!in_array($event_year, $event_years)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_year']) && wp_strip_all_tags($_GET['filter_event_year']) == $event_year) ? 'selected' : ''; ?>><?php echo $event_year; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if ($all_months) {
                        ?>
                        <select name="filter_event_month">
                            <option value="">Month of Race</option>
                            <?php
                            foreach ($all_months as $event_month => $month_name) {
                                ?>
                                <option value="<?php echo ($event_month < 10) ? '0' . $event_month : $event_month; ?>" <?php echo (!in_array($event_month, $event_months)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_month']) && wp_strip_all_tags($_GET['filter_event_month']) == $event_month) ? 'selected' : ''; ?>><?php echo $month_name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if (!empty($weather_factors)) {
                        ?>
                        <select name="filter_main_weather_factor">
                            <option value="">Main Weather Factor</option>
                            <?php
                            foreach ($weather_factors as $weather_factor) {
                                ?>
                                <option value="<?php echo $weather_factor; ?>" <?php echo (isset($_GET['filter_main_weather_factor']) && wp_strip_all_tags($_GET['filter_main_weather_factor']) == $weather_factor) ? 'selected' : ''; ?>><?php echo ucwords($weather_factor); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if ($all_distance_categories) {
                        ?>
                        <select name="filter_distance_category">
                            <option value="">Distance Category</option>
                            <?php
                            foreach ($all_distance_categories as $distance_category_id => $distance_category) {
                                ?>
                                <option value="<?php echo $distance_category_id; ?>" <?php echo (!in_array($distance_category_id, $distance_categories)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_distance_category']) && wp_strip_all_tags($_GET['filter_distance_category']) == $distance_category_id) ? 'selected' : ''; ?>><?php echo $distance_category['name']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    ?>

                    <p>
                        <button type="submit">Filter</button>
                    </p>
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>"
                       class="button button-secondary clear_filters">Clear
                        Filters</a>
                </form>
                <!--- End of Filter Form --->

                <?php
            }
        //}

    }

    /*
     * Get championship table filter form
     */
    public function get_championship_table_filter_form($race_league_id=0){

        $can_access = $this->can_access();
        //if(!is_wp_error($can_access)) {

            $vars = array(
                'race_league' => $race_league_id
            );

            $race_stats = $this->get_leader_board($vars);

            // get all distance categories from race stats
            $all_distance_categories = $this->get_distance_categories();
            $distance_categories = array_unique(array_column($race_stats, 'distance_category'));

            // get all user countries from race stats
            $all_countries = $this->get_countries();
            $user_countries = array_unique(array_column($race_stats, 'user_country'));

            // get all user genders from race stats
            $all_genders = array('male' => 'Male', 'female' => 'Female');
            $user_genders = array_unique(array_column($race_stats, 'user_gender'));

            // get all age groups from race stats
            $all_age_groups = $this->get_age_groups();
            $age_groups = array_unique(array_column($race_stats, 'age_group'));

            // get all event countries from race stats
            $event_countries = array_unique(array_column($race_stats, 'event_country'));

            // doing the array_map 'strtolower' so we dont get options outputting twice in caps and non-caps (bug from import)
            $weather_factors = array_unique(array_map("strtolower", array_column($race_stats, 'main_weather_factor')));

            ?>

            <!-- Search, Filter & 'Show Me' action buttons -->
            <div class="action-buttons">
                <div class="two-thirds">
                    <div class="columns-2">
                        <div class="item">
                            <button <?php /* 'show me' functionality only for fr or th */
                            echo (!in_array($race_league_id, array(1, 0))) ? 'disabled' : ''; ?>
                                    class="show_me" data-current-user-id="<?php echo $this->get_wocrl_user_id(); ?>">Show Me
                            </button>
                        </div>
                        <div class="item">
                            <button class="filter_league_table">Filters</button>
                        </div>
                        <div class="clear"></div>
                    </div>
                </div>
                <div class="one-third">
                    <div class="item">
                        <button class="search_league_table"><i class="fa fa-search" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="clear"></div>
            </div>


            <!--- Filter Form --->
            <form id="raceLeagueFilter" method="get">
                <?php
                if ($all_countries) {
                    ?>
                    <select name="filter_user_country">
                        <option value="">Country of Origin</option>
                        <?php
                        foreach ($all_countries as $user_country_code => $user_country) {
                            ?>
                            <option value="<?php echo $user_country_code; ?>" <?php echo (!in_array($user_country_code, $user_countries)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_user_country']) && wp_strip_all_tags($_GET['filter_user_country']) == $user_country_code) ? 'selected' : ''; ?>><?php echo $user_country; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>
                <?php
                if ($all_countries) {
                    ?>
                    <select name="filter_event_country">
                        <option value="">Country of Race</option>
                        <?php
                        foreach ($all_countries as $event_country_code => $event_country) {
                            ?>
                            <option value="<?php echo $event_country_code; ?>" <?php echo (!in_array($event_country_code, $event_countries)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_event_country']) && wp_strip_all_tags($_GET['filter_event_country']) == $event_country_code) ? 'selected' : ''; ?>><?php echo $event_country; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>
                <?php
                if ($all_age_groups) {
                    ?>
                    <select name="filter_age_group">
                        <option value="">Age Group</option>
                        <?php
                        foreach ($all_age_groups as $age_group_id => $age_group) {
                            ?>
                            <option value="<?php echo $age_group_id; ?>" <?php echo (!in_array($age_group_id, $age_groups)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_age_group']) && wp_strip_all_tags($_GET['filter_age_group']) == "{$age_group_id}") ? 'selected' : ''; ?>><?php echo $age_group['name']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>
                <?php
                if ($all_genders) {
                    ?>
                    <select name="filter_gender">
                        <option value="">Gender</option>
                        <?php
                        foreach ($all_genders as $gender_key => $user_gender) {
                            ?>
                            <option value="<?php echo $gender_key; ?>" <?php echo (!in_array($gender_key, $user_genders)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_gender']) && wp_strip_all_tags($_GET['filter_gender']) == $gender_key) ? 'selected' : ''; ?>><?php echo ucfirst($user_gender); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>
                <?php
                if ($all_distance_categories) {
                    ?>
                    <select name="filter_distance_category">
                        <option value="">Distance Category</option>
                        <?php
                        foreach ($all_distance_categories as $distance_category_id => $distance_category) {
                            ?>
                            <option value="<?php echo $distance_category_id; ?>" <?php echo (!in_array($distance_category_id, $distance_categories)) ? 'disabled' : ''; ?> <?php echo (isset($_GET['filter_distance_category']) && wp_strip_all_tags($_GET['filter_distance_category']) == "{$distance_category_id}") ? 'selected' : ''; ?>><?php echo $distance_category['name']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>
                <?php
                if (!empty($weather_factors)) {
                    ?>
                    <select name="filter_main_weather_factor">
                        <option value="">Main Weather Factor</option>
                        <?php
                        foreach ($weather_factors as $weather_factor) {
                            ?>
                            <option value="<?php echo $weather_factor; ?>" <?php echo (isset($_GET['filter_main_weather_factor']) && wp_strip_all_tags($_GET['filter_main_weather_factor']) == $weather_factor) ? 'selected' : ''; ?>><?php echo ucwords($weather_factor); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <?php
                }
                ?>

                <?php
                // hidden fields for the search so users can filter search results
                if (isset($_GET['filter_racer_name']) && wp_strip_all_tags($_GET['filter_racer_name']) != '') {
                    ?>
                    <input type="hidden" name="filter_racer_name" value="<?php echo wp_strip_all_tags($_GET['filter_racer_name']); ?>">
                    <?php
                }
                ?>

                <p>
                    <button type="submit">Filter</button>
                </p>
                <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="button button-secondary">Clear Filters</a>
            </form>
            <!--- End of Filter Form --->

            <!--- Search Form --->
            <form id="raceLeagueSearch" method="get">
                <?php
                // hidden fields for all the filters so users can search filtered results
                foreach ($_GET as $key => $value) {
                    if ($key == 'filter_racer_name') {
                        continue;
                    }
                    ?>
                    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo wp_strip_all_tags($value); ?>">
                    <?php
                }
                ?>
                <input type="text" name="filter_racer_name"
                       value="<?php echo (isset($_GET['filter_racer_name']) && wp_strip_all_tags($_GET['filter_racer_name']) != '') ? wp_strip_all_tags($_GET['filter_racer_name']) : ''; ?>"
                       placeholder="Search (name)">
                <button type="submit">Search</button>
            </form>
            <!--- End of Search Form --->

            <?php
        //}


    }

    /*
     * Get filter query for league tables
     */
    public function get_league_table_filter_query($race_stats){
        // Filter by event
        $filter_event_id = (isset($_GET['filter_event_id']) && $_GET['filter_event_id'] != '') ? wp_strip_all_tags($_GET['filter_event_id']) : false;
        if($filter_event_id){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_event_id) {

                return ($value->event_id == $filter_event_id);

            });
        }

        // Filter by community
        $filter_community_id = (isset($_GET['filter_community_id']) && $_GET['filter_community_id'] != '') ? wp_strip_all_tags($_GET['filter_community_id']) : false;
        if($filter_community_id){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_community_id) {

                return ($value->community_id == $filter_community_id);

            });
        }

        // Filter by country of origin
        $filter_user_country = (isset($_GET['filter_user_country']) && $_GET['filter_user_country'] != '') ? wp_strip_all_tags($_GET['filter_user_country']) : false;
        if($filter_user_country){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_user_country) {

                return ($value->user_country == $filter_user_country);

            });
        }

        // Filter by country of race
        $filter_event_country = (isset($_GET['filter_event_country']) && $_GET['filter_event_country'] != '') ? wp_strip_all_tags($_GET['filter_event_country']) : false;
        if( $filter_event_country){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_event_country) {

                return ($value->event_country == $filter_event_country);

            });
        }

        // Filter by gender
        $filter_gender = (isset($_GET['filter_gender']) && $_GET['filter_gender'] != '') ? wp_strip_all_tags($_GET['filter_gender']) : false;
        if($filter_gender){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_gender) {

                return ($value->user_gender == $filter_gender);

            });
        }

        // Filter by age group
        $filter_age_group = (isset($_GET['filter_age_group']) && $_GET['filter_age_group'] != '') ? wp_strip_all_tags($_GET['filter_age_group']) : false;
        if($filter_age_group){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_age_group) {

                return ($value->age_group == $filter_age_group);

            });
        }

        // Filter by distance category
        $filter_distance_category = (isset($_GET['filter_distance_category']) && $_GET['filter_distance_category'] != '') ? wp_strip_all_tags($_GET['filter_distance_category']) : false;
        if($filter_distance_category || $filter_distance_category === '0'){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_distance_category) {

                return ($value->distance_category == $filter_distance_category);

            });
        }

        // Filter by event year
        $filter_event_year = (isset($_GET['filter_event_year']) && $_GET['filter_event_year'] != '') ? wp_strip_all_tags($_GET['filter_event_year']) : false;
        if($filter_event_year){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_event_year) {

                return (date('Y',strtotime($value->event_start_date)) == $filter_event_year);

            });
        }

        // Filter by event month
        $filter_event_month = (isset($_GET['filter_event_month']) && $_GET['filter_event_month'] != '') ? wp_strip_all_tags($_GET['filter_event_month']) : false;
        if($filter_event_month){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_event_month) {

                return (date('m',strtotime($value->event_start_date)) == $filter_event_month);

            });
        }

        // Filter by league
        $filter_race_league = (isset($_GET['filter_race_league'])) ? wp_strip_all_tags($_GET['filter_race_league']) : false;
        if($filter_race_league === '1' || $filter_race_league === '0'){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_race_league) {

                return ($value->race_league == $filter_race_league);

            });
        }

        // Filter by main weather factor
        $filter_main_weather_factor = (isset($_GET['filter_main_weather_factor']) && $_GET['filter_main_weather_factor'] != '') ? wp_strip_all_tags($_GET['filter_main_weather_factor']) : false;
        if($filter_main_weather_factor){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_main_weather_factor) {

                return (strtolower($value->main_weather_factor) == $filter_main_weather_factor);

            });
        }

        // Filter by racer name
        $filter_racer_name = (isset($_GET['filter_racer_name']) && $_GET['filter_racer_name'] != '') ? wp_strip_all_tags($_GET['filter_racer_name']) : false;
        if($filter_racer_name){
            $race_stats= array_filter($race_stats, function ($value) use ($filter_racer_name) {

                $racer_name = $value->user_first_name.' '.$value->user_last_name;
                return (strpos(strtolower($racer_name), strtolower($filter_racer_name)) !== false);

            });
        }

        return $race_stats;
    }

    /*
     * Get race stats from WOCRL API
     */
    public function get_leader_board($vars){

        try{
            $response = $this->client->request('GET', "getleaderboard/{$this->api_key}/",[
                'query' => $vars
            ]);

            return $this->parse_guzzle_response($response);
        }catch (GuzzleException $e) {// RequestException
            return false;
        }
    }

    //</editor-fold>
}

$WOCRL_API = new WOCRL_PLUGIN_API;