<?php

namespace SGN\FormsBundle\Utils;

class Serializor
{


    /**
     * Converts the Doctrine Entity into a JSON Representation.
     *
     * @param object  $object    The Object (Typically a Doctrine Entity) to convert to an array
     * @param integer $depth     The Depth of the object graph to pursue
     * @param array   $whitelist List of entity=>array(parameters) to convert
     * @param array   $blacklist List of entity=>array(parameters) to skip
     *
     * @return string
     */
    public static function json_encode($object, $depth = 1, $whitelist = array(), $blacklist = array())
    {
        return json_encode(self::toArray($object, $depth, $whitelist, $blacklist));
    }

    /**
     * Serializes our Doctrine Entities.
     *
     * This is the primary entry point, because it assists with handling collections
     * as the primary Object
     *
     * @param object  $object    The Object (Typically a Doctrine Entity) to convert to an array
     * @param integer $depth     The Depth of the object graph to pursue
     * @param array   $whitelist List of entity=>array(parameters) to convert
     * @param array   $blacklist List of entity=>array(parameters) to skip
     *
     * @return NULL|Array
     */
    public static function toArray($object, $depth = 1, $whitelist = array(), $blacklist = array())
    {
        // If we drop below depth 0, just return NULL

        if ($depth < 0) {
            return;
        }

        // If this is an array, we need to loop through the values
        if (is_array($object) === true) {
            // Somthing to Hold Return Values
            $anArray = array();

            // The Loop
            foreach ($object as $value) {
                // Store the results
                $anArray[] = self::arrayizor($value, $depth, $whitelist, $blacklist);
            }

            // Return it
            return $anArray;
        } else {
            // Just return it
            return self::arrayizor($object, $depth, $whitelist, $blacklist);
        }
    }
}
