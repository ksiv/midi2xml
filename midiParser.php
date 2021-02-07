<?php

namespace midi;

require_once ("./midiEventTable.php");


/*
Konstantin S. Ivanov Copyright

MIDI file parser 
- the very first idea is to decode midi stream into messages    
- current solution is to form output as XML for further processings

*/

if(defined("_class_midi_is_included")) {
  // do nothing since the class is already included  
} else {
  define("_class_midi_is_included",1);
// {{{


    function xml_node($name, $value){
        return "<".$name.">".$value."</".$name.">";
    }




class midiParser {
var $contents;          // depricated
var $out;               // output
var $track_counter=0;   //
var $running_status;   // save status for simultaneous event handling

    // Constructor
    function midi_parser(){
        $this->search_tracks();
    }
    // Returnes amount of track including meta-info-tracks
    function num_tracks(){
        return $this->track_counter;
    }
    // parser output 
    function output(){
        // return $this->contents;

        return "<pre>".$this->out."</pre>";
/*
return  '<?xml version="1.0" encoding="ISO-8859-1"?>
     <?xml-stylesheet type="text/xsl" href="midi.xsl"?>
         <midi_file>'.$this->out.'</midi_file>';
*/
    }

    // searches tracks and runs track parser
    function search_tracks(){
        // this should not be here year I know

         $filename = "./sol-si.mid";

        //Simple file parser 

        $fd = fopen($filename, "rb");           // open file
        $buffer_size=4;                         // initial buffer is set to 4
        $step="head";                           // initial step type is set to head


        // Read file searching for track 
        while($buffer=fread($fd, $buffer_size)){
            // [TODO] Step iterator $$ segment name
            $this->contents.=$buffer;

            $chunk_buffer.= $buffer;

            switch ($step){
                // Case chunk head (header)
                case "head":
                    $out.= "<";
                    $out.=  $head =$buffer;     // [!IMPORTANT] this hack turns head to a string 
                    $out.= "> ";
                    $step="size";
                break;

                // case chunk size
                // converts bin2dec
                // setup buffer size  
                case "size":

                    $size=hexdec(bin2hex($buffer));
                    //$out.=  "<size> ".$size."</size>";
                    $out.=  xml_node("size", $size);
                    //$out.=  "size_hex [ ".bin2hex($buffer)."]<br>";
                    $buffer_size=$size;
                    $step="data";
                break;

                // case chunk data
                // output data buffer + whole chunk
                case "data":
                    //$out.=  "data [ ".$buffer."]<br>";
                    //  $out.=  "<data><![CDATA[".substr($buffer,0,100)."]]></data>";
                    $out.=xml_node("data","data segment explaned farther");
                    // [TODO] remove print
                    //print  "hex_data [ ".substr(bin2hex($buffer),0,1850)."......]<br>";
                    //$out.=  "whole chunk [ ".$chunk_buffer."]<br>";
                    $chunk_buffer="";
                    $buffer_size=4;
                    $step="head";
                    // [TODO] make sure it works right as these are in Big endian
                    if ($head=="MThd"){
                        // Get midi type [0/1/2]
                        $midi_type=substr($buffer,0,2);
                        $midi_type=hexdec(bin2hex($midi_type));
                        $out.= "<midi_type>".$midi_type."</midi_type>";
                        // Get NumTracks
                        $NumTracks=substr($buffer,2,2);
                        $NumTracks=hexdec(bin2hex($NumTracks));
                        $out.= "<NumTracks>".$NumTracks."</NumTracks>";
                        // Get Division PPQN (Pulses Per Qurter Note)
                        $Division=substr($buffer,4,2);
                        $Division=hexdec(bin2hex($Division));
                        $out.= "<Division>".$Division."</Division>";
                        $out.= "</MThd>";

                    }elseif ($head=="MTrk"){
                        // Count track
                        ++$this->track_counter;
                        // Parse track
                        $out.="
                    <Track_data>".$this->parse_track_data($buffer). "</Track_data>";
                        $out.="</MTrk>";
                    }else {
                        $out.="Look guys, a trash track has been found!!!";
                    }
                break;
            }// switch end

        }
        //$contents = fread($handle, filesize($filename));
        fclose($fd);
        $this->out=$out;

    }// function end



    function parse_track_data($buffer){


    // Parses given track data segment
        $i=0;
        $step="time";
        $delta=0;
        $bf_length= strlen($buffer);
        $out="";
        $midi_events = midiEventTable::$midiEvents;
        while($i<$bf_length){


            //count bin/hex/dec at the beginning
            $statusBin=$buffer[$i];
            $statusHex=bin2hex($statusBin);
            $upcaseHex=strtoupper($statusHex);
            $statusDec=hexdec($statusHex);

            switch($step){

                case "time":
                     // time VLQ (variable length quantity)
                     // increase delta time
                    $delta+=$statusDec;
                     //
                     // Check control bit
                    if ($statusDec==0) {                               // case zero time
                        //$out.= "&nbsp;&nbsp;&nbsp;&nbsp;";
                        $out.="
                        <time>".$delta."</time>";
                        $step="status";
                        $delta=0;
                    } elseif ($statusDec < 128 && $statusDec > 0){           // case the last byte
                        $out.="
                        <time>".$delta."</time>";
                        $step="status";
                        $delta=0;
                    }else{                                      // case continue counting time
                        $delta-=128;
                    }

                break;

                case "status":
                    $status_string =$statusHex;
                    // [TODO] unicase
                    // {{{
                    // (NON midi) Meta-Events
                    //
                    // }}}
                    // here should be some switch
                    // {{{
                    $non_MIDI = array("ff01","ff02","ff03","ff04","ff05","ff06","ff07","ff08","ff09","ff7f");
                    // still non_MIDI
                    if(in_array($status_string, $non_MIDI)){
                        $out.="<non-MIDI>".$status_string."</non-MIDI>";
                        $step="length";
                    }elseif ($statusHex=="f0"){
                        $step="length";
                        $out.="[SYSEX]";
                    // tempo
                    }elseif($status_string=="ff5103"){
                        //$out.='<type>"'.$status_string.'"</type>';
                        $step="tempo";
                    // TODO find out what is it?
                    }elseif($status_string=="4751"){
                        $out.='<type>"'.$status_string.'"</type>';
                        $step="time";
                        $status_complete="";
                    // SMPTE
                    }elseif($status_string=="ff5405"){
                        //$out.='<type>"'.$status_string.'"</type>';
                        $step="SMPTE";
                        $status_complete="";
                    }elseif($status_string=="ff5804"){
                        //$out.='<type>"'.$status_string.'"</type>';
                        $step="time_signature";
                        $status_complete="";
                    }elseif($status_string=="ff5902"){
                        //$out.='<type>"'.$status_string.'"</type>';
                        $step="key_signature";
                        $status_complete="";
                    }elseif($status_string=="ff2001" or $status_string=="ff2101"){
                        //$out.='<type>"'.$status_string.'"</type>';
                        $step="obsolete";
                        $status_complete="";
                    }elseif($status_string=="0a" or $status_string=="06"){
                        $out.='<type>"'.$status_string.'"</type>';
                        $step="unknown";

                    // MIDI events
                    // Simultaneous event processin
                    // no notes yet catch zeroes
                    // goto status`
                    }elseif(hexdec($status_string)==0){
                        $step="status";

                    // event type has been set already
                    // running status
                    }elseif(hexdec($status_string)<=127&&hexdec($status_string)>0){
                            $out.="<MIDI><event>".$midi_events[strtoupper($this->running_status)][0]."</event><rs>On</rs><second_byte>".$statusHex."</second_byte>";
                        //  Here we determine whether the third byte will be
                        if ((hexdec($this->running_status)>=128 && hexdec($this->running_status)<=191)
                            or (hexdec($this->running_status)>=224 && hexdec($this->running_status)<=239)){
                            $third_byte=true;
                            $step="third_byte";
                        }else{
                            $third_byte=false;
                            $step="time";
                            $out.="</MIDI>";
                        }
                    // Vouice Category Stauses (Instruments)
                    }elseif($statusDec>=128&&$statusDec<=239){
                        // save running status, cos it can be ommited further
                        $this->running_status=$status_string;
                        $out.="<MIDI><event>".$midi_events[$upcaseHex][0].'</event>';
                        $step="second_byte";
                        $status_complete="";
                        $sb=$midi_events[$status_string][1];
                        //  Here we determine whether the third byte will be
                        if ((hexdec($status_string)>=128 && hexdec($status_string)<=191) 
                        or (hexdec($status_string)>=224 && hexdec($status_string)<=239)){
                            $third_byte=true;
                        }else{
                            $third_byte=false;
                        }
                    // status has not been determined till it reached X in lenght
                    }elseif( strlen($status_string)>=25 ){
                            $out.="<too_long>***</too_long>";
                        $i=strlen($buffer);// stop cycle
                    }else{
                        $out.="<incomplete>\"".$status_string."\"</incomplete>";
                    }

                    // }}}
                break;

                case "length":
                    // [TODO] there is a problem with next step (date or time) to rewrite 
                    // depends on midi vs non_MIDI
                    // length (VLQ)
                    // Clear control bit means the end of sequence 
                    $delta+=$statusDec;
                    //
                    // Handle 00 in the begginning and in the end
                    if ($statusDec==0){
                        $out.= "<length>".$delta."</length>";
                        $step="time";
                        $data_to_read=$delta;
                        $delta=0;
                    }elseif($statusDec < 128){
                    //if ($dec & 128){
                    //if ($bin & 00000001){
                        $out.= "<length>".$delta."</length>";
                        $step="data";
                        $data_to_read=$delta;
                        $delta=0;
                    }else{
                        $delta-=128;

                    }
                //$out.= hexdec(bin2hex($buffer[$i]));
                break;

                case "data":
                    --$data_to_read;
                    //
                    //print "<br>[".$data_to_read."][".$step."]";
                    $data_buffer.= $to_strin =$statusHex;
                    if ($data_to_read==0){
                        $out.="<data>\"".$data_buffer."\"</data>";
                        $step="time";
                        $data_buffer=0;
                    }
                break;

                case "tempo":
                    if(!isset($tempo_counter)){
                        $tempo_counter=1;
                        $tempo=$statusDec;
                    }else{
                        ++$tempo_counter;
                        $tempo+=$statusDec;
                    }
                    // read three bytes of tempo
                    if($tempo_counter==3){
                        $out.="<tempo>\"".$tempo."\"</tempo>";
                        $step="time";
                        unset($tempo_counter);
                        unset($tempo);

                    }
                break;

                case "SMPTE":
                    if(!isset($tmp_counter)){
                        $tmp_counter=1;
                        $smpte=$statusHex;
                    }else{
                        ++$tmp_counter;
                        $smpte.=$statusHex;
                    }
                    // read three bytes of tempo
                    if($tmp_counter==5){
                        $out.="<SMPTE>\"".$smpte."\"</SMPTE>";
                        $step="time";
                        unset($tmp_counter);
                        unset($smpte);

                    }
                break;

                case "time_signature":
                    if(!isset($tmp_counter)){
                        $tmp_counter=1;
                        $time_signature=$statusHex;
                    }else{
                        ++$tmp_counter;
                        $time_signature.=$statusHex;
                    }
                    // read three bytes of tempo
                    if($tmp_counter=="f4"){
                        $out.="<time_signature>\"".$time_signature."\"</time_signature>";
                        $step="time";
                        unset($tmp_counter);
                        unset($time_signature);

                    }
                break;

                case "key_signature":
                    if(!isset($tmp_counter)){
                        $tmp_counter=1;
                        $key_signature=$statusHex;
                    }else{
                        ++$tmp_counter;
                        $key_signature.=$statusHex;
                    }
                    // read three bytes of tempo
                    if($tmp_counter==2){
                        $out.="<key_signature>\"".$key_signature."\"</key_signature>";
                        $step="time";
                        unset($tmp_counter);
                        unset($key_signature);

                    }
                break;


                case "obsolete":
                        $out.="<obsolete>\"".$statusHex."\"</obsolete>";
                        $step="time";
                        $status_complete="";
                break;

                case "unknown":
                        $out.="<unknown>\"".$statusHex."\"</unknown>";
                        $step="time";
                        $status_complete="";
                break;

                case "third_byte":
                    $out.="<third_byte>\"".$tb."=".$statusHex."\"</third_byte></MIDI>";
                    $step="time";
                    $status_complete="";
                    
                break;
// 
                case "second_byte":
                    $out.="<second_byte>\"".$midi_events[$upcaseHex][1]."=".$statusHex."\"</second_byte>";
                    if ($third_byte==true){
                        $step="third_byte";
                    }else{
                        $step="time";
                        $out.="</MIDI>";
                    }
                    $status_complete="";
                    $third_byte=false;

                break;
            }
            ++$i;
        }
        $out.= "done";
        return $out;

    }
}

}

$my_midi = new midiParser;
$my_midi->search_tracks();
echo $my_midi->num_tracks();
echo $my_midi->output();

?>