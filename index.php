<?php


ini_set('display_errors', 0);
ini_set('display_startup_errors', 0); 
// error_reporting(E_ALL);

ini_set('max_execution_time', '4000');
ini_set('memory_limit', '-1');

//  $clientId   = $_REQUEST['ClientId'];
 $FromDate   = date_format(date_create($_REQUEST['FromDate']),'Y-m-d');
 $ToDate     = date_format(date_create($_REQUEST['ToDate']),'Y-m-d');

$host       =   "localhost"; 
$username   =   "root";
$password   =   "dial@mas123";
$db_name    =   "db_dialdesk";

$con = mysql_connect("192.168.10.5","root","vicidialnow","false",128);
mysql_select_db("asterisk",$con)or die("cannot select DB");

$dd = mysql_connect("$host", "$username", "$password")or die("cannot connect"); 
mysql_select_db("$db_name",$dd)or die("cannot select DB");

function usage_details($clientId,$FromDate,$ToDate,$dd,$con)
{
    $usage_details = array();
   
       
        $BalanceMaster = mysql_fetch_assoc(mysql_query("select * from `balance_master` where clientId='$clientId'  limit 1"));

        $PlanDetails = mysql_fetch_assoc(mysql_query("select * from `plan_master` where Id='{$BalanceMaster['PlanId']}' limit 1"));
            $start_date = $FromDate; 
            $end_date = $BalanceMaster['end_date'];
            $balance = $BalanceMaster['MainBalance'];
            $PeriodType = strtolower($PlanDetails['PeriodType']);

        if($BalanceMaster['PlanId'] !="" && !empty($start_date) && $start_date!='0000-00-00')
        {
            
            $ClientInfo = mysql_fetch_assoc(mysql_query("select * from `registration_master` where company_id='$clientId' limit 1"));
            $Campagn=$ClientInfo['campaignid'];
            
            

            $usage_details['client']= $ClientInfo['company_name'];
            
            if($PlanDetails['first_minute']=='Enable')
            {
                //$ib_first_min = $PlanDetails['ib_first_min'];
                $ib_first_min='1';
                $ob_first_min='1';
            }
            else
            {
                $ib_first_min='0';
                $ob_first_min='0';
            }
            $ib_pulse_sec = $PlanDetails['pulse_day_shift'];
            $ibn_pulse_sec = $PlanDetails['pulse_night_shift'];
            $ib_pulse_rate = $PlanDetails['rate_per_pulse_day_shift'];
            $ibn_pulse_rate = $PlanDetails['rate_per_pulse_night_shift'];
            $ifmp = ceil(60/$ib_pulse_sec);
            //$ob_first_min = $PlanDetails['ob_first_min'];
            $ob_pulse_sec = $PlanDetails['pulse_outbound_call_shift'];
            $ob_pulse_rate = $PlanDetails['rate_per_pulse_outbound_call_shift'];
            $ofmp = ceil(60/$ob_pulse_sec); 
            $bill_month = "";
            
            
            
            // Inbound Call duration details
          
            $InboundDetails=mysql_query("select if(t3.`talk_sec` is null,t2.length_in_sec,t3.`talk_sec`) length_in_sec, phone_number,call_date from `vicidial_closer_log` t2 left join vicidial_agent_log t3 on t2.uniqueid=t3.uniqueid where t2.user !='VDCL' and t2.campaign_id in ($Campagn) AND DATE(call_date) between '$start_date' AND '$ToDate' ",$con);
            
            
            $OutboundDetails=mysql_query("select (va.talk_sec-va.dead_sec) length_in_sec,if(LENGTH(phone_number)>'12',LEFT(phone_number,10),RIGHT(phone_number,10)) phone_number,call_date from `vicidial_log` v join vicidial_agent_log va on v.uniqueid=va.uniqueid where length_in_sec!='0' and v.user !='VDAD' and v.campaign_id in ($Campagn) AND DATE(call_date) between '$start_date' AND '$ToDate' ",$con);
            $SMSDetails = mysql_query("SELECT DATE_FORMAT(CallDate,'%d %b %y') `CallDate1`,CallDate,CallTime,CallFrom,Unit FROM `billing_master` WHERE clientId='$clientId' AND DedType='SMS' AND date(CallDate) between '$start_date' AND '$ToDate'");
            
            $EmailDetails = mysql_query("SELECT DATE_FORMAT(CallDate,'%d %b %y') `CallDate1`,CallDate,CallTime,CallFrom,Unit FROM `billing_master` WHERE clientId='$clientId' AND DedType='Email' AND date(CallDate) between '$start_date' AND '$ToDate' ");
            $billing_IVR = mysql_query("SELECT DATE_FORMAT(call_time,'%d %b %y') `CallDate1`,call_time CallDate,time(call_time) CallTime,1 Unit,source_number CallFrom FROM `rx_log` WHERE clientId='$clientId'  AND DATE(call_time) BETWEEN '$start_date' AND '$ToDate'");
            
            // $VFODetails = mysql_query("SELECT DATE_FORMAT(CallDate,'%d %b %y') `CallDate1`,CallDate,CallTime,CallFrom,Unit "
            //       . "FROM `billing_master` WHERE clientId='$clientId' AND DedType='VFO' AND date(CallDate) between '$start_date' AND '$ToDate' ");
            if($clientId=='383') 
            {
                $VFODetails =mysql_query("select DATE_FORMAT(ivrtime,'%d %b %y') `CallDate1`,ivrtime CallDate,time(ivrtime) CallTime,left(t1.source_number,10) CallFrom,t2.length_in_sec,CEIL(t2.length_in_sec/60) Unit from sbarro_data t1 left join call_log t2 on t1.uniqueid=t2.uniqueid where destination_number!='' and date(ivrtime) between '$start_date' AND '$ToDate'",$con);
            }

            $TinAmount=0;
            $TouAmount=0;
            $TvfAmount=0;
            $TsmAmount=0;
            $TemAmount=0;
            $TivAmount=0;
            $period_arr = array();
            $balance_arr = array();
            $package_bal = array();
            
            if($PeriodType=='month')
            {
                $package_bal =  round($balance/12);
                for($i=1;$i<=12;$i++)
                {
                        $period_arr[$i] =    date('Y-m-d',strtotime($start_date ." + $i months")); 
                        //add additional balance
                        $last_date = date('Y-m-d',strtotime($start_date ." + $i months"));
                        $last_date = date('Y-m-d',strtotime($start_date ." - 1 days"));
                        $sel_add_bal = "SELECT * FROM `waiver_master` WHERE clientId='$clientId' AND  DATE(end_date) = '$last_date'";
                        $sel_add_bal_rsc = mysql_query($sel_add_bal,$dd);
                        while($add_bal_arr = mysql_fetch_assoc($sel_add_bal_rsc))
                        {
                            if(!empty($add_bal_arr['Balance']))
                            {
                                $package_bal += $add_bal_arr['Balance'];
                            }
                        }
                        $balance_arr[$i] = $package_bal;
                }
            }
            
            else if($PeriodType=='quater')
            {
                $package_bal =  round($balance/4); 
                    for($i=1;$i<=4;$i++)
                {
                        $period_arr[$i] =    date('Y-m-d',strtotime($start_date .' + '.($i*3).' months')); 
                        $last_date = date('Y-m-d',strtotime($start_date .' + '.($i*3).' months'));
                        $last_date = date('Y-m-d',strtotime($start_date ." - 1 days"));
                        $sel_add_bal = "SELECT * FROM `waiver_master` WHERE clientId='$clientId' AND  DATE(end_date) = '$last_date'";
                        $sel_add_bal_rsc = mysql_query($sel_add_bal,$dd);
                        while($add_bal_arr = mysql_fetch_assoc($sel_add_bal_rsc))
                        {
                            if(!empty($add_bal_arr['Balance']))
                            {
                                $package_bal += $add_bal_arr['Balance'];
                            }
                        }
                        $balance_arr[$i] = $package_bal;
                        //echo $last_date;exit;
                }
            
            }
            else
            {
                $package_bal =  round($balance);
                $period_arr[1] =    $end_date." 23:59:59"; 
                
                    $last_date = $end_date;
                    $last_date = date('Y-m-d',strtotime($start_date ." - 1 days"));
                    $sel_add_bal = "SELECT * FROM `waiver_master` WHERE clientId='$clientId' AND  DATE(end_date) = '$last_date'";
                    $sel_add_bal_rsc = mysql_query($sel_add_bal,$dd);
                    while($add_bal_arr = mysql_fetch_assoc($sel_add_bal_rsc))
                    {
                        if(!empty($add_bal_arr['Balance']))
                        {
                            $package_bal += $add_bal_arr['Balance'];
                        }
                    }
                
                $balance_arr[1] = $package_bal; 
            }
        
            //print_r($period_arr); exit;
            $html1 = ""; $html1N = "";
            if(mysql_num_rows($InboundDetails) > 0)
            {
                $html1N .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (INBOUND NIGHT)</h5>";
                $html1 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (INBOUND)</h5>";
                $htmlH ='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $htmlH .="<tr>";
                $htmlH .="<th>Date</th>";
                $htmlH .="<th>Time</th>";
                $htmlH .="<th>Call From</th>";
                $htmlH .="<th>Talk Time</th>";
                $htmlH .="<th>Pulse</th>";
                $htmlH .="<th>Rate</th>";
                $htmlH .="</tr>";
                $html1N .=$htmlH;
                $html1 .=$htmlH;
                
                $InTotalPulse  =0;
                $InTotalTalkTime =0;
                $InTotalTalkRate =0;
                
                $InTotalPulseNight  =0;
                $InTotalTalkTimeNight =0;
                $InTotalTalkRateNight =0;
                
                while($inbDurArr = mysql_fetch_assoc($InboundDetails))
                {
                    //print_r($inbDurArr); die;
                    
                    $inbDuration=round($inbDurArr['length_in_sec']-$inbDurArr['queue_seconds']);
                    $amount = 0; 
                    $convrt_pulse = $inbDuration/$ib_pulse_sec;
                    if($ib_first_min=='1')
                    {
                        
                    // $minute = floor($inbDuration-$ifmp);
                        //echo $inbDuration;exit;
                        
                        if($convrt_pulse>$ifmp)
                        {
                        $subsequent = ($convrt_pulse-$ifmp); 
                        $total_pulse = $ifmp+ceil($subsequent);
                        }
                        else
                        {
                        $total_pulse = $ifmp;
                        }
                    
                        
                        if(strtotime(date('H:i:s',strtotime($inbDurArr['call_date'])))>=strtotime('20:00:00') 
                                || strtotime(date('H:i:s',strtotime($inbDurArr['call_date'])))<=strtotime('08:00:00'))
                        {
                            $amount = $ibn_pulse_rate*$total_pulse; 
                            //echo "ibn_"."$ibn_pulse_rate*$total_pulse";exit;
                        }
                        else
                        {
                            $amount = $ib_pulse_rate*$total_pulse; 
                            //echo "ib_"."$ib_pulse_rate*$total_pulse";exit;
                        }
                    }
                    else
                    {
                        
                        $total_pulse = ceil($inbDuration/$ib_pulse_sec); 
                        if(strtotime(date('H:i:s',strtotime($inbDurArr['call_date'])))>=strtotime('20:00:00') 
                                || strtotime(date('H:i:s',strtotime($inbDurArr['call_date'])))<strtotime('08:00:00'))
                        {
                            $amount = $total_pulse*($ibn_pulse_rate);
                        }
                        else
                        {
                            $amount = $total_pulse*($ib_pulse_rate);   
                        }
                    }
                    
                    $start_date1 = $start_date." 00:00:00"; 
                    $call_date = strtotime(date('Y-m-d H:i:s',strtotime($inbDurArr['call_date'])));
                    foreach($period_arr as $end_date)
                    {
                    //echo "{$inbDurArr['call_date']}>=strtotime($start_date1) && {$inbDurArr['call_date']} <$end_date"; exit;
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['InTotalAmount'] += $amount;
                            break;
                        }
                        else
                        {
                            //echo "{$inbDurArr['call_date']}>=strtotime($start_date1) && {$inbDurArr['call_date']} <$end_date"; exit;
                            $start_date1 =   $end_date; 
                        }
                        $Inbnew_cycle_start = $start_date1; 
                        $Inbnew_cycle_end = $end_date;
                    }
                    $inbDurArr['Duration'] = $inbDuration;
                    $inbDurArr['amount'] = $amount;
                    $inbDurArr['unit'] = $total_pulse;
                    $inbData[$inbDurArr['call_date']][] = $inbDurArr;
                }
                //print_r($inbData); exit;
                foreach($inbData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($Inbnew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $htmlD ="<tr>"; //exit;
                            $htmlD .="<td>".date('Y-m-d',strtotime($inb['call_date']))."</td>"; 
                            $htmlD .="<td>".date('H:i:s',strtotime($inb['call_date']))."</td>";
                            $htmlD .="<td>".$inb['phone_number']."</td>";
                            $htmlD .="<td>".$inb['Duration']."</td>";
                            $htmlD .="<td>".$inb['unit']."</td>";
                            $htmlD .="<td>".round($inb['amount'],2)."</td>";
                            $htmlD .="</tr>";
                        
                            
                        
                            if(strtotime(date('H:i:s',strtotime($inb['call_date'])))>=strtotime('20:00:00') 
                                    || strtotime(date('H:i:s',strtotime($inb['call_date'])))<strtotime('08:00:00'))
                            {
                                //$TinAmountNight = round($inb['amount'],2);
                                $html1N .= $htmlD;
                                $inTotalSumaryUnitNight += $inb['unit'];
                                $InTotalTalkTimeNight += $inb['Duration'];
                                $InTotalPulseNight += $inb['unit'];
                                $InTotalTalkRateNight += round($inb['amount'],2);
                            }
                            else
                            {
                                $html1 .= $htmlD;
                                $inTotalSumaryUnit += $inb['unit'];
                                $InTotalTalkTime += $inb['Duration'];
                                $InTotalPulse += $inb['unit'];
                                 
                                $InTotalTalkRate += round($inb['amount'],2);
                            }
                        
                    }
                    }
                    
                    
                }

                $usage_details['inb_pulse']=$InTotalPulse;
                $usage_details['inb_pulse_night']=$InTotalPulseNight;
                //echo $html1; exit;
                //print_r($data); exit;
                //$html .="<tr><td colspan='3' ><b>Total</b></td><td><b>{$InTotalPulse}</b></td><td><b>{$InTotalAmount}</b></td></tr>";
                $html1 .="<tr><td colspan='3' align=\"right\"><b>Total</b></td>";
                $html1 .="<td><b> {$InTotalTalkTime}</b></td>";
                $html1 .="<td><b> {$InTotalPulse}</b></td>";
                $html1 .="<td><b> ".round($InTotalTalkRate,2)."</b></td>";
                $html1 .="</tr></table>";
                
                $html1N .="<tr><td colspan='3' align=\"right\"><b>Total</b></td>";
                $html1N .="<td><b> {$InTotalTalkTimeNight}</b></td>";
                $html1N .="<td><b> {$InTotalPulseNight}</b></td>";
                $html1N .="<td><b> ".round($InTotalTalkRateNight,2)."</b></td>";
                $html1N .="</tr></table>";
            }

            //echo "get"; exit;
            //echo $html1; exit;
            
            
            //$html .="<br/><br/>";

        
            if(mysql_num_rows($OutboundDetails) > 0)
            {
                $html2 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (OUTBOUND)</h5>";
                $html2 .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html2 .="<tr>";
                $html2 .="<th>Date</th>";
                $html2 .="<th>Time</th>";
                $html2 .="<th>Call From</th>";
                $html2 .="<th>Talk Time</th>";
                $html2 .="<th>Pulse</th>";
                $html2 .="<th>Rate</th>";
                $html2 .="</tr>";

                $OutTotalPulse  =0;
                $OutTotalAmount =0;
                $OutTotalTalkTime =0;
                $OutTotalTalkRate =0;
                while($inb = mysql_fetch_assoc($OutboundDetails)){
                
                    $callLength = round($inb['length_in_sec']);
                    $amount = 0; 
                    $convrt_pulse = $callLength/$ob_pulse_sec;
                    if($ob_first_min=='1')
                    {
                        if($convrt_pulse>$ofmp)
                        {
                        $subsequent = ceil($convrt_pulse-$ofmp); 
                        $total_pulse = $ofmp+$subsequent;
                        }
                        else
                        {
                        $total_pulse = $ofmp;
                        }
                        
                        $amount = $ob_pulse_rate*$total_pulse;
                    }
                    else
                    {
                        $total_pulse = ceil($callLength/$ob_pulse_sec);
                        $amount = $total_pulse*($ob_pulse_rate);
                    }

                    $start_date1 = $start_date;
                    $call_date = strtotime(date('Y-m-d',strtotime($inb['call_date'])));
                    foreach($period_arr as $end_date)
                    {
                        
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['OutTotalTalkRate'] +=round($amount,2);
                            break;
                        }
                        else
                        {
                            $start_date1 =   $end_date; 
                        }
                        $Outnew_cycle_start = $start_date1;
                        $Outnew_cycle_end = $end_date;
                    }
                    
                    $inb['amount'] = $amount;
                    $inb['unit'] = $total_pulse;
                    $OutData[$inb['call_date']][] = $inb;
                    
                }
                
            

                foreach($OutData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($Outnew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $html2 .="<tr>";
                            $html2 .="<td>".date('Y-m-d',strtotime($inb['call_date']))."</td>";
                            $html2 .="<td>".date('H:i:s',strtotime($inb['call_date']))."</td>";
                            $html2 .="<td>".$inb['phone_number']."</td>";
                            $html2 .="<td>".$inb['length_in_sec']."</td>";
                            $html2 .="<td>".$inb['unit']."</td>";
                            $html2 .="<td>".round($inb['amount'],2)."</td>";
                            $html2 .="</tr>";

                            $OutTotalTalkTime += $inb['length_in_sec'];
                            $OutTotalPulse += $inb['unit'];
                            $OutTotalTalkRate += round($inb['amount'],2);

                            $TouAmount = round($inb['amount'],2);
                            $OutTotalSumaryUnit += $inb['unit'];
                        
                        }
                    }
                    
                    
                }
                
                //print_r($html2 );exit;
                //$html .="<tr><td colspan='3' ><b>Total</b></td><td><b>{$OutTotalPulse}</b></td><td><b>{$OutTotalAmount}</b></td></tr>";
                $html2 .="<tr><td colspan='3' ><b>Total</b></td>";
                $html2 .="<td><b>{$OutTotalTalkTime}</b></td>";
                $html2 .="<td><b>{$OutTotalPulse}</b></td>";
                $html2 .="<td><b>{$OutTotalTalkRate}</b></td>";
                $html2 .="</tr></table>";
            }
            $usage_details['out_pulse']=$OutTotalPulse;
            //$html .="<br/><br/>";
            
        

            if(mysql_num_rows($SMSDetails) > 0)
            {
                $html3 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (SMS)</h5>";
                $html3 .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html3 .="<tr>";
                $html3 .="<th>Date</th>";
                $html3 .="<th>Time</th>";
                $html3 .="<th>Call From</th>";
                $html3 .="<th>Pulse</th>";
                $html3 .="<th>Rate</th>";
                $html3 .="</tr>";
                $SMSTotal = 0;
                while($inb = mysql_fetch_assoc($SMSDetails)){
                    
                    $start_date1 = $start_date;
                    $call_date = strtotime(date('Y-m-d',strtotime($inb['CallDate'])));
                    foreach($period_arr as $end_date)
                    {    
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['SmsCharge'] += round($inb['Unit']*$PlanDetails['SMSCharge'],2);
                            break;
                        }
                        else
                        {
                            $start_date1 =   $end_date; 
                        }
                        $SMSnew_cycle_start = $start_date1;
                        $SMSnew_cycle_end = $end_date;
                    }
                    $inb['amount'] = round($inb['Unit']*$PlanDetails['SMSCharge'],2);;
                    $SmsData[$inb['CallDate']][] = $inb;
                }
                
                foreach($SmsData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($SMSnew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $html3 .="<tr>";
                            $html3 .="<td>".$inb['CallDate1']."</td>";
                            $html3 .="<td>".$inb['CallTime']."</td>";
                            $html3 .="<td>".$inb['CallFrom']."</td>";
                            $html3 .="<td>".$inb['Unit']."</td>";
                            $html3 .="<td>".round($inb['Unit']*$PlanDetails['SMSCharge'],2)."</td>";
                            $html3 .="</tr>";

                            $SMSTotal += $inb['Unit']; 
                            $SMS['Unit'] += $inb['Unit']; 
                        }
                    }
                    
                }
                
                
                $html3 .="<tr><td colspan='5' ><b>Total Vol {$SMSTotal}</b></td></tr>";
                $html3 .="</table>";
            }
            
            $usage_details['sms_total']=$SMSTotal;

            //$html .="<br/><br/>";

            if(mysql_num_rows($EmailDetails) > 0)
            {
                $html4 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (EMAIL)</h5>";
                $html4 .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html4 .="<tr>";
                $html4 .="<th>Date</th>";
                $html4 .="<th>Time</th>";
                $html4 .="<th>Call From</th>";
                $html4 .="<th>Pulse</th>";
                $html4 .="<th>Rate</th>";
                $html4 .="</tr>";
                $EmailTotal = 0;
                while($inb = mysql_fetch_assoc($EmailDetails)){
                    
                    $start_date1 = $start_date;
                    $call_date = strtotime(date('Y-m-d',strtotime($inb['CallDate'])));
                    foreach($period_arr as $end_date)
                    {    
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['EmailCharge'] += round($inb['Unit']*$PlanDetails['EmailCharge'],2);
                            break;
                        }
                        else
                        {
                            $start_date1 =   $end_date; 
                        }
                        $Emailnew_cycle_start = $start_date1;
                        $Emailnew_cycle_end = $end_date;
                    }
                    $inb['amount'] = round($inb['Unit']*$PlanDetails['EmailCharge'],2);;
                    $EmailData[$inb['CallDate']][] = $inb;
                    
                }
                foreach($EmailData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($Emailnew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $html4 .="<tr>";
                        $html4 .="<td>".$inb['CallDate1']."</td>";
                        $html4 .="<td>".$inb['CallTime']."</td>";
                        $html4 .="<td>".$inb['CallFrom']."</td>";
                        $html4 .="<td>".$inb['Unit']."</td>";
                        $html4 .="<td>".round($inb['Unit']*$PlanDetails['EmailCharge'],2)."</td>";
                        $html4 .="</tr>";
                        $EmailTotal += $inb['Unit'];
                        $Email['Unit'] += $inb['Unit'];
                        }
                    }    
                }
                $html4 .="<tr><td colspan='5' ><b>Total Vol {$EmailTotal}</b></td></tr>";
                $html4 .="</table>";
            }

            //$html .="<br/><br/>";
            $usage_details['EmailTotal']=$EmailTotal;


            if(mysql_num_rows($VFODetails) > 0)
            {
                $html5 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (VFO)</h5>";
                $html5 .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html5 .="<tr>";
                $html5 .="<th>Date</th>";
                $html5 .="<th>Time</th>";
                $html5 .="<th>Call From</th>";
                $html5 .="<th>Pulse</th>";
                $html5 .="<th>Rate</th>";
                $html5 .="</tr>";
                $VFOTotal = 0;
                while($inb = mysql_fetch_assoc($VFODetails)){
                    
                    
                    $start_date1 = $start_date;
                    $call_date = strtotime(date('Y-m-d',strtotime($inb['CallDate'])));
                    foreach($period_arr as $end_date)
                    {    
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['VFOCallCharge'] += round($inb['Unit']*$PlanDetails['VFOCallCharge'],2);
                            break;
                        }
                        else
                        {
                            $start_date1 =   $end_date; 
                        }
                        $Vfonew_cycle_start = $start_date1;
                        $Vfonew_cycle_end = $end_date;
                    }
                    $inb['amount'] = round($inb['Unit']*$PlanDetails['VFOCallCharge'],2);;
                    $VfoData[$inb['CallDate']][] = $inb;
                    
                }
                
                foreach($VfoData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($Vfonew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $html5 .="<tr>";
                            $html5 .="<td>".$inb['CallDate1']."</td>";
                            $html5 .="<td>".$inb['CallTime']."</td>";
                            $html5 .="<td>".$inb['CallFrom']."</td>";
                            $html5 .="<td>".$inb['Unit']."</td>";
                            $html5 .="<td>".round($inb['Unit']*$PlanDetails['VFOCallCharge'],2)."</td>";
                            $html5 .="</tr>";
                            $VFOTotal += $inb['Unit'];
                            $VFO['Unit'] += $inb['Unit'];
                        }
                    }    
                }
                
                
            
                $html5 .="<tr><td colspan='5' ><b>Total Vol {$VFOTotal}</b></td></tr>";
                $html5 .="</table>";
            }
            $data = array();
            
            $bal_carray = 0; $pending_bal = 0;
            foreach($period_arr as $key=>$end_date)
            {
                $mont_aval = $balance_arr[$key];
                //echo ' - ';
                $month_used = round($data[$end_date]['InTotalAmount'],2) +
                        round($data[$end_date]['OutTotalTalkRate'],2)+
                        round($data[$end_date]['SmsCharge'],2)+
                        round($data[$end_date]['EmailCharge'],2)+
                        round($data[$end_date]['VFOCallCharge'],2);
                
                $mont_bal =  round($mont_aval - $month_used,2);
                
                if($mont_bal<0)
                {
                    $pending_bal += $mont_bal;
                }
                
                if(strtolower($PlanDetails['TransferAfterRental'])=='yes')
                {
                    if($mont_bal>=0)
                    {
                    $bal_carray +=$mont_bal;  
                    }
                }
                echo '<br/>';
            }
            
            $usage_details['VFOTotal']=$VFOTotal;
            if(mysql_num_rows($billing_IVR) > 0)
            {
                $html6 .="<h5 style='font-size:11pt;' >{$ClientInfo['company_name']} (IVR)</h5>";
                $html6 .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html6 .="<tr>";
                $html6 .="<th>Date</th>";
                $html6 .="<th>Time</th>";
                $html6 .="<th>Call From</th>";
                $html6 .="<th>Pulse</th>";
                $html6 .="<th>Rate</th>";
                $html6 .="</tr>";
                $IVRTotal = 0;
                while($inb = mysql_fetch_assoc($billing_IVR)){
                    
                    $start_date1 = $start_date;
                    $call_date = strtotime(date('Y-m-d',strtotime($inb['CallDate'])));
                    foreach($period_arr as $end_date)
                    {    
                        if($call_date>=strtotime($start_date1) && $call_date<strtotime($end_date))
                        {
                            $data[$end_date]['IVRCharge'] += round(1*$PlanDetails['IVR_Charge'],2);
                            break;
                        }
                        else
                        {
                            $start_date1 =   $end_date; 
                        }
                        $SMSnew_cycle_start = $start_date1;
                        $SMSnew_cycle_end = $end_date;
                    }
                    $inb['amount'] = round(1*$PlanDetails['IVR_Charge'],2);
                    $IVRData[$inb['CallDate']][] = $inb;
                }
                
                foreach($IVRData as $call_date=>$inb_arr)
                {
                    $call_date = substr($call_date,0,10);
                    foreach($inb_arr as $inb)
                    {
                        if(strtotime($call_date)>=strtotime($SMSnew_cycle_start) && strtotime($call_date)>=strtotime($FromDate))
                        {
                            $html6 .="<tr>";
                            $html6 .="<td>".$inb['CallDate1']."</td>";
                            $html6 .="<td>".$inb['CallTime']."</td>";
                            $html6 .="<td>".$inb['CallFrom']."</td>";
                            $html6 .="<td>1</td>";
                            $html6 .="<td>".round(1*$PlanDetails['IVR_Charge'],2)."</td>";
                            $html6 .="</tr>";

                            $IVRTotal += 1; 
                            $IVR['Unit'] += 1; 
                        }
                    }
                    
                }
                
                
                $html6 .="<tr><td colspan='5' ><b>Total Vol {$IVRTotal}</b></td></tr>";
                $html6 .="</table>";
            }
            $usage_details['IVRTotal']=$IVRTotal;
            //print_r($bal_carray); exit;
            
            if($inTotalSumaryUnit !="") {$TinAmount=round($inTotalSumaryUnit*$ib_pulse_rate,2);}
            if($inTotalSumaryUnitNight !="") { $TinAmountNight=round($inTotalSumaryUnitNight*$ibn_pulse_rate,2);}
            if($OutTotalSumaryUnit !="") {$TouAmount=$OutTotalTalkRate;}
            if(!empty($VFO['Unit'])) {$TvfAmount=round($VFO['Unit']*$PlanDetails['VFOCallCharge'],2);}
            if(!empty($SMS['Unit'])) {$TsmAmount=round($SMS['Unit']*$PlanDetails['SMSCharge'],2);}
            if(!empty($Email['Unit'])) {$TemAmount=round($Email['Unit']*$PlanDetails['EmailCharge'],2);}
            if(!empty($IVR['Unit'])) {$TivAmount=round($IVR['Unit']*$PlanDetails['IVR_Charge'],2);}

            $usage_details['VFOCallCharge']=$PlanDetails['VFOCallCharge'];
           
            
            
            $html .="
                    <table border='0' width='600' cellpadding='2' cellspacing='2' style='font-size:11pt;' >
                        <tr>
                            <td colspan='2' rowspan='4' ></td><td colspan='4' rowspan='4'>
                                <img src='http://dialdesk.co.in/dialdesk/app/webroot/billing_statement/logo.jpg'>
                            </td>
                        </tr>
                    </table>
            ";
            
            $html .="
                    <table border='0' width='600' cellpadding='2' cellspacing='2' style='font-size:11pt;' >
                        <tr>
                            <td colspan='2' rowspan='2' ></td><td colspan='4' rowspan='2' >
                                A UNIT OF ISPARK DATA CONNECT PVT LTD
                            </td>
                        </tr>
                    </table>
            ";
            
            
            $html .="
                    <table border='1' width='600' cellpadding='2' cellspacing='2' style='font-size:11pt;' >
                    <tr><td colspan='7' style='font-size:15pt;background-color:#607d8b;color:#fff;font-weight:bold;'>Client Details</td></tr>
                    <tr>
                        <th>Company</th>
                        <th colspan='3' >Address</th>
                        <th>registered Mobile No</th>
                        <th>Registered Email Id</th>
                        <th>authorised person</th>
                    </tr>
                    <tr>
                        <td>{$ClientInfo['company_name']}</td>
                        <td colspan='3' >{$ClientInfo['reg_office_address1']}</td>
                        <td>{$ClientInfo['phone_no']}</td>
                        <td>{$ClientInfo['email']}</td>
                        <td>{$ClientInfo['auth_person']}</td>
                    </tr>
                    </table>
            ";

        $html .="<table><tr><td>&nbsp;</td></tr></table>";
            
            $TotUseBalance=(round($inTotalSumaryUnit*$PlanDetails['InboundCallCharge'],2)
                    +round($inTotalSumaryUnitNight*$PlanDetails['InboundCallChargeNight'],2)
                    +round($OutTotalSumaryUnit*$PlanDetails['OutboundCallCharge'],2)
                    +round($VFO['Unit']*$PlanDetails['VFOCallCharge'],2)
                    +round($SMS['Unit']*$PlanDetails['SMSCharge'],2)
                    +round($Email['Unit']*$PlanDetails['EmailCharge'],2)
                    +round($IVR['Unit']*$PlanDetails['IVR_Charge'],2));

            $usage_details['OutboundCallCharge']=$PlanDetails['OutboundCallCharge'];
            
            $html .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
            $html .="<tr><td colspan='8' style='font-size:15pt;background-color:#607d8b;color:#fff;font-weight:bold;'>Plan Details</td></tr>";
            
            $html .="<tr>";
            $html .="<th>Plan Name</th>";
            $html .="<th>Start Date</th>";
            $html .="<th>End Date</th>";
            $html .="<th>Balance</th>";
            $html .="<th>Validity</th>";
            $html .="<th>".$PlanDetails['PeriodType']. " Balance</th>";
            $html .="<th>Available</th>";
            $html .="<th>Used</th>";
            $html .="</tr>";
            $html .="<tr>";
            $html .="<td>{$PlanDetails['PlanName']}</td>";
            $html .="<td>{$BalanceMaster['start_date']}</td>";
            $html .="<td>{$BalanceMaster['end_date']}</td>";
            $html .="<td>".round($pending_bal+$bal_carray)."</td>";
            $html .="<td>".$PlanDetails['RentalPeriod'].' '.$PlanDetails['PeriodType']."</td>";
            $html .="<td>".$package_bal."</td>";
            $used = ($TinAmount+$TinAmountNight+$TouAmount+$TvfAmount+$TsmAmount+$TemAmount+$TivAmount);
            //if(intval($BalanceMaster['Used']) >= intval($BalanceMaster['MainBalance'])){
                //$html .="<td>0</td>";
            //}
            //else{
                $html .="<td>".($package_bal-$used)."</td>";
            //}
            
            
            $html .="<td>".$used."</td>";
            $html .="</tr>";
            $html .="</table>";
            
            
            
            
            
            $html .="<table><tr><td>&nbsp;</td></tr></table>";
            
            if($inTotalSumaryUnit !="" || $inTotalSumaryUnitNight !="" || $OutTotalSumaryUnit !="" || $VFO['Unit'] !="" || $SMS['Unit'] !="" || $Email['Unit'] !="") {
                $html .='<table border="1" width="600" cellpadding="2" cellspacing="2" style="font-size:11pt;" >';
                $html .="<tr><td colspan='5' style='font-size:15pt;background-color:#607d8b;color:#fff;font-weight:bold;'>Summary</td></tr>";
                $html .="<tr>";
                $html .="<th>Description</th>";
                $html .="<th>Vol./Pulse</th>";
                $html .="<th>Rate</th>";
                $html .="<th colspan='2' >Amount</th>";
                $html .="</tr>";
            }
            
            if($inTotalSumaryUnit !="") {
                
                $html .="<tr>";
                $html .="<td>ICB</td>";
                $html .="<td>{$inTotalSumaryUnit}</td>";
                $usage_details['ib_pulse_rate']=$ib_pulse_rate;
                $html .="<td>".round($ib_pulse_rate,6)."  Rs./ $ib_pulse_sec Sec</td>";
                $html .="<td colspan='2'>".round($TinAmount,2)."</td>";
                $html .="</tr>";
            }
            if($inTotalSumaryUnitNight !="") {
            
                $html .="<tr>";
                $html .="<td>ICB Night</td>";
                $html .="<td>{$inTotalSumaryUnitNight}</td>";
                $usage_details['ibn_pulse_rate']=$ibn_pulse_rate;

                $html .="<td>".round($ibn_pulse_rate,6)."  Rs./ $ib_pulse_sec Sec</td>";
                $html .="<td colspan='2'>".round($TinAmountNight,2)."</td>";
                $html .="</tr>";
            }

            if($OutTotalSumaryUnit !="") {
                
                $html .="<tr>";
                $html .="<td>OCB</td>";
                $html .="<td>{$OutTotalSumaryUnit}</td>";
                $usage_details['ob_pulse_rate']=$ob_pulse_rate;
                $html .="<td>".round($ob_pulse_rate,6)."  Rs./ {$ob_pulse_sec} Min</td>";
                $html .="<td colspan='2'>".round($OutTotalTalkRate,2)."</td>";
                $html .="</tr>";
            }

            if(!empty($VFO['Unit'])) {
                
                $html .="<tr>";
                $html .="<td>VFO</td>";
                $usage_details['VFO']=$VFO['Unit'];

                $html .="<td>{$VFO['Unit']}</td>";
                $html .="<td>{$PlanDetails['VFOCallCharge']}  Rs./Min </td>";
                $html .="<td colspan='2'>".round($VFO['Unit']*$PlanDetails['VFOCallCharge'],2)."</td>";
                $html .="</tr>";
            }

            if(!empty($SMS['Unit'])) {
                
                $html .="<tr>";
                $html .="<td>SMS</td>";
                $html .="<td>{$SMS['Unit']}</td>";
                $usage_details['SMSCharge']=$PlanDetails['SMSCharge'];
                $html .="<td>{$PlanDetails['SMSCharge']}  Rs./Min </td>";
                $html .="<td colspan='2'>".round($SMS['Unit']*$PlanDetails['SMSCharge'],2)."</td>";
                $html .="</tr>";
            }

            if(!empty($Email['Unit'])) {
                
                $html .="<tr>";
                $html .="<td>Email</td>";
                $html .="<td>{$Email['Unit']}</td>";
                $usage_details['EmailCharge']=$PlanDetails['EmailCharge'];

                $html .="<td>{$PlanDetails['EmailCharge']}  Rs./Min </td>";
                $html .="<td colspan='2'>".round($Email['Unit']*$PlanDetails['EmailCharge'],2)."</td>";
                $html .="</tr>";
            }
            
            if(!empty($IVR['Unit'])) {
                
                $html .="<tr>";
                $html .="<td>IVR</td>";
                $html .="<td>{$IVR['Unit']}</td>";
                $usage_details['IVR_Charge']=$IVR['IVR_Charge'];
                $html .="<td>{$PlanDetails['IVR_Charge']}  Rs./Min </td>";
                $html .="<td colspan='2'>".round($IVR['Unit']*$PlanDetails['IVR_Charge'],2)."</td>";
                $html .="</tr>";
            }
            
            $html .="<tr>";
            $html .="<td>TOTAL ({$FromDate} / {$ToDate})</td>";
            $html .="<td></td>";
            $html .="<td></td>";
            $html .="<td colspan='2'>".($TinAmount+$TinAmountNight+$TouAmount+$TvfAmount+$TsmAmount+$TemAmount+$TivAmount)."</td>";
            $html .="</tr>";

            $html .="</table>";
            
            //$html .="<br/>";

            
             

            

        }  
        //print_r($usage_details); exit;    
    return $usage_details;   
    
}


    $all_client_data = array();

    // for all active clients

    // $all_clients_qry = "SELECT company_id FROM registration_master rm 
    //  WHERE rm.status='A' AND company_id='301' AND campaignid IS NOT NULL  ORDER BY company_name limit 5";

    $all_clients_qry = "SELECT company_id FROM registration_master rm 
    WHERE rm.status='A' and campaignid IS NOT NULL  ORDER BY company_name ";

    $all_clients_qry_rsc = mysql_query($all_clients_qry,$dd);
    while($all_clients_arr = mysql_fetch_assoc($all_clients_qry_rsc))
    {
        $clientId= $all_clients_arr['company_id'];
        $all_client_data[$clientId] = usage_details($clientId,$FromDate,$ToDate,$dd,$con); 
    }

        //print_r($all_client_data);

      


$fileName = "combined_bill_statement_from_".$FromDate."_to_".$ToDate;
header("Content-Type: application/vnd.ms-excel; name='excel'");
header("Content-type: application/octet-stream");
header("Content-Disposition: attachment; filename=".$fileName.".xls");
header("Pragma: no-cache");
header("Expires: 0");

?>

<table border="1">
                <tr>
                    <td colspan='1' style='background-color:#fff;'></td>
                    <td  colspan='2' style=' text-align:center;background-color:#d9d9d9;color:#000;font-weight:bold;'>Period</td>
                    <td  colspan='8' style=' text-align:center;background-color:#ffc000;color:#000;font-weight:bold;'>Unit Consumed</td>
                    <td  colspan='2' style=' text-align:center;background-color:#fff;color:#000;font-weight:bold;'></td>
                    <td  colspan='8' style=' text-align:center;background-color:#edf31a;color:#000;font-weight:bold;'>Per Unit Rate</td>
                    <td  colspan='2' style=' text-align:center;background-color:#fff;color:#000;font-weight:bold;'></td>
                    <td  colspan='8' style=' text-align:center;background-color:#92d050;color:#000;font-weight:bold;'>Value</td>
                </tr>
                <tr style='background-color:#d9d9d9;color:#000;font-weight:bold;'>
                    <th>Client Name</th>
                    <th>From</th>
                    <th>To</th>

                    <th>Inbound Call</th>
                    <th>Outbound Call</th>
                    <th>Night Shift l/b</th>
                    <th>SMS</th>
                    <th>EMAIL</th>
                    <th>MISSCALL</th>
                    <th>Call Forwarding</th>
                    <th>IVR Automation</th>

                    <th></th>
                    <th></th>
                    <th>Inbound Call</th>
                    <th>Outbound Call</th>
                    <th>Night Shift l/b</th>
                    <th>SMS</th>
                    <th>EMAIL</th>
                    <th>MISSCALL</th>
                    <th>Call Forwarding</th>
                    <th>IVR Automation</th>

                    <th></th>
                    <th></th>
                    <th>Inbound Call</th>
                    <th>Outbound Call</th>
                    <th>Night Shift l/b</th>
                    <th>SMS</th>
                    <th>EMAIL</th>
                    <th>MISSCALL</th>
                    <th>Call Forwarding</th>
                    <th>IVR Automation</th>
                </tr>
                <?php     
                
                foreach($all_client_data as $clientid)
                {
                    //print_r($clientid['client']);
            
                    // unit consumed
               
                    if(!empty($clientid['client']))
                    {
                        
                        
                    $unit_Inbound_Call=$clientid['inb_pulse'];
                    $unit_Outbound_Call=$clientid['out_pulse'];
                    $unit_Night_Shift_ib=$clientid['inb_pulse_night'];
                    $unit_SMS=$clientid['sms_total'];
                    $unit_EMAIL=$clientid['EmailTotal'];
                    $unit_MISSCALL=0;
                    $unit_Call_Forwarding=$clientid['VFOTotal'];
                    $unit_IVR_Automation = $clientid['IVRTotal'];


                    // Plan master  for per unit rate


                    $Inbound_Call=$clientid['ib_pulse_rate']; 
                    $Outbound_Call=$clientid['ob_pulse_rate'];
                    $Night_Shift_ib=$clientid['ibn_pulse_rate'];
                    $SMS=$clientid['SMSCharge'];
                    $EMAIL=$clientid['EmailCharge'];
                    $MISSCALL=0;
                    $Call_Forwarding=$clientid['VFOCallCharge'];
                    $IVR_Automation=$clientid['IVR_Charge'];
                    

                    // value

                    
                    $value_Inbound_Call=$unit_Inbound_Call*$Inbound_Call;
                    $value_Outbound_Call=$unit_Outbound_Call*$Outbound_Call;
                    $value_Night_Shift_ib=$unit_Night_Shift_ib*$Night_Shift_ib;
                    $value_SMS=$unit_SMS*$SMS;
                    $value_EMAIL=$unit_EMAIL*$EMAIL;
                    $value_MISSCALL=$unit_MISSCALL*$MISSCALL;
                    $value_Call_Forwarding=$unit_Call_Forwarding*$Call_Forwarding;
                    $value_IVR_Automation = $unit_IVR_Automation*$IVR_Automation;

                ?>





                    <tr>
                    <td><?php echo $clientid['client'];?></td>
                    <td><?php echo date_format(date_create($FromDate),"d-M-Y");?></td>
                    <td><?php echo date_format(date_create($ToDate),"d-M-Y");?></td>
                    <td><?php echo $unit_Inbound_Call;?></td>
                    <td><?php echo $unit_Outbound_Call;?></td>
                    <td><?php echo $unit_Night_Shift_ib; ?></td>
                    <td><?php echo $unit_SMS; ?></td>
                    <td><?php echo $unit_EMAIL; ?></td>
                    <td><?php echo $unit_MISSCALL; ?></td>
                    <td><?php echo $unit_Call_Forwarding; ?></td>
                    <td><?php echo $unit_IVR_Automation; ?></td>
                    <td></td>
                    <td></td>
                    <td><?php echo round($Inbound_Call,2);?></td>
                    <td><?php echo round($Outbound_Call,2);?></td>
                    <td><?php echo round($Night_Shift_ib,2); ?></td>
                    <td><?php echo round($SMS,2); ?></td>
                    <td><?php echo round($EMAIL,2); ?></td>
                    <td><?php echo round($MISSCALL,2); ?></td>
                    <td><?php echo round($Call_Forwarding,2); ?></td>
                    <td><?php echo round($IVR_Automation,2); ?></td>
                    <td></td>
                    <td></td>
                    <td><?php echo round($value_Inbound_Call,2);?></td>
                    <td><?php echo round($value_Outbound_Call,2);?></td>
                    <td><?php echo round($value_Night_Shift_ib,2); ?></td>
                    <td><?php echo round($value_SMS,2); ?></td>
                    <td><?php echo round($value_EMAIL,2); ?></td>
                    <td><?php echo round($value_MISSCALL,2); ?></td>
                    <td><?php echo round($value_Call_Forwarding,2); ?></td>
                    <td><?php echo round($value_IVR_Automation,2); ?></td>

                </tr>
            <?php } }?>
            
        </table>
        <?php
        exit;
        ?>
