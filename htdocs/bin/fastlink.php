#!/usr/bin/php

<?

if (isset($_SERVER["REMOTE_ADDR"])) {
  print "This script must be run from command line\n";
  exit;
}

require (dirname($_SERVER["SCRIPT_FILENAME"]).'/../config.php');
require (dirname($_SERVER["SCRIPT_FILENAME"]).'/../lib/lib.php');

if (file_exists($linker_lock_file)) {
  print "Lock file exist! Another linker is running?\n";
  exit;
}

touch($linker_lock_file);

connect_to_sql($sql_host,$sql_base,$sql_user,$sql_pass);

// ������� ��������� ������� ��� ���������
mysql_query("create temporary table `tmp` (
   `id` bigint(64) NOT NULL,
   `msgid` varchar(128) NULL,
   `reply` varchar(128) NULL,
   `recieved` datetime NULL,
   `area` text NULL,
   `thread` varchar(128) NULL,
   `level` bigint(64) NOT NULL,
   `inthread` bigint(64) NOT NULL,
   `fromname` varchar(128) NULL,
   `fromaddr` varchar(128) NULL,
   `hash` varchar(128) NULL,
   `date` varchar(128) NULL,
   `subject` text NULL,
    KEY `recieved_key` (`recieved`),
    KEY `thread_recieved_key` (`thread`,`recieved`),
    KEY `thread_key` (`thread`),
    KEY `reply_key` (`reply`),
    KEY `reply_recieved_key` (`reply`,`recieved`),
    KEY `msgid_key` (`msgid`)
)  CHARSET=utf8");



if (isset($argv['1'])) {
  $area=$argv['1'];
} else {
  $area="";
}

if ($area) { 
  $query=("select upper(area) as area from `messages` where thread='' and area='$area' group by area;");
} else { 
  $query=("select upper(area) as area from `messages` where thread='' and area!='' group by area order by area;");
}
$result=mysql_query($query);
while ($row=mysql_fetch_object($result)) {

  // �� ������ ������ ������� ��������� �������
  mysql_query("delete from `tmp`;");
  // ��������� ��������� ������� �������� �� ��������� ���
  mysql_query("insert into `tmp` (id, msgid, reply, recieved,area,thread,level,inthread,fromname,fromaddr,hash,date,subject) 
                           select id, msgid, reply, recieved,area,thread,level,inthread,fromname,fromaddr,hash,date,subject
                            from `messages` where area=\"$row->area\";");

  print $row->area." ";
  $area_end=0;
  while ($area_end==0){ //����� ������. ������ ����� ������ �������� ��� ������� � ���� ���� (� ����� ���� � ��������� ������ ���� ������. ����� �� �������� ����� � ����)
    $result2=mysql_query("select msgid,reply,subject,fromname,fromaddr,date,recieved,hash from `tmp` where thread='' order by recieved limit 1;");
    if (mysql_num_rows($result2)){
      $row2=mysql_fetch_object($result2);
      $thread_info = array ( //��������� ���� � ����� ����������� � ��������� ���������
	'area'			=> $row->area,
	'thread'		=> $row2->msgid,
	'subject'		=> $row2->subject,
	'author'		=> $row2->fromname,
	'author_address'	=> $row2->fromaddr,
	'author_date'		=> $row2->date,
	'last_author'		=> $row2->fromname,
	'last_author_address'	=> $row2->fromaddr,
	'last_author_date'	=> $row2->date,
	'num'			=> 0,
	'lastupdate'		=> $row2->recieved,
	'last_hash'		=> $row2->hash
      );
      if ($row2->reply==0) { //���� reply ������. �������������, �������� ������ � �����
        if(!mysql_num_rows(mysql_query("select msgid from `tmp` where reply='$row2->msgid';"))){ // ������ � ������������ � �����.
          $thread_info=new_thread($thread_info); // ������� ���� � ����� ����� �������
	  print("n");
        } else { //��������� ������, �� �� ������������ � �����. ������� �������
          $thread_info=set_thread($thread_info); // ������������� ������� ������, ������� ����������� ���� � �����
	  print("N");
        }
      }else{ //��������� �� ������ � �����
	$result3=mysql_query("select thread,msgid,level,inthread,subject from `tmp` where msgid='$row2->reply';"); //���� ��������, �� ������� ��� ��������
        if(!mysql_num_rows($result3)){ // ������ �� �������. �������������, ���� ��� ������.
          if(!mysql_num_rows(mysql_query("select msgid from `tmp` where reply='$row2->msgid';"))){//� ������������ � �����.
            $thread_info=new_thread($thread_info); // ������� ���� � ����� ����� �������
	    print("t");
          } else { //���� ������, �� �� ������������ � �����
            $thread_info=set_thread($thread_info); // ������������� ������� ������, ������� ����������� ���� � �����
	    print("T");
          }
        } else { //� ��� ���� ������, �� ������� �������� ��� ���������.
	  $row3=mysql_fetch_object($result3);
          if ($row3->thread) { // ��������� ������ � ��� ������������ ����.
            if (mysql_num_rows(mysql_query("select msgid from `tmp` where reply='$row2->msgid';"))){ //��������� �� ��������� � ������� �����.
              //��������� ��������:
              $thread_info['thread']=find_begin($row2->msgid); // ������� ������ �����
              $thread_info=set_thread($thread_info); // ������������� ������� ������, ������� ����������� ���� � �����
  	      print("s");
//!��� ��� ����� ��������� �������, ����� ��� ������ ����� ��� � ����� ��������� �� ������ �����.
//!���� ���-�� � ������ ������� ������ ����� ���������, �� �������� ���������� ����������� � �������� ������-�����.
//!����� ����� ����� ��������� ���������.

            } else { // ��������� ��������� � �����, �� ���� ����� �� ��������. ������ ��������� � ����.
//!����� ���� ����� � ����, ��� ����� ���� ��� ����� ������, ���������� ������� �� ���� � �� �� ������ �� �����.
//!� ����� ������ ����� ����� ��� ������� ��������������.
              if (match_text($thread_info['subject'],$row3->subject)){ //���� ���� ����������� �� �������
                $thread_info['thread']=$row3->thread;
	        $thread_info=add_to_thread($thread_info,$row2->msgid,$row2->reply,$row3->level,$row3->inthread);
	      } else { //���� ���� ������ ��������, �� ��� ����� ����
        	$thread_info=new_thread($thread_info); // ������� ���� � ����� ����� �������
	      }
  	      print("f");
            }
          } else { //��������� ������ � ��������������� ����
//!����������, ������������� ����� ���� ������ ��������� ������� ����. ����� �� ����� ������ ��������������� �ӣ, ����������
//!������ ��������� ������� �� ��������� ��������� � ������� �� � ����
            $thread_info['thread']=find_begin($row2->msgid); // ������� ������ �����
            $thread_info=set_thread($thread_info); // ������������� ������� ������, ������� ����������� ���� � �����
	    print("S");
          }
        }
      }
    } else {
      $area_end=1;
    } 
    // ������� ���� � ����� � ������� threads
    save_thread($thread_info);
  }
//� ���� ���������
  print "\n";
}

unlink($linker_lock_file);


function find_begin($msgid){
  $result=mysql_query("select reply from `tmp` where msgid=\"$msgid\"");
  if(mysql_num_rows($result)){
    $row=mysql_fetch_object($result);
    if ($row->reply) {
      $return=find_begin($row->reply);
      if (!$return) {
        $return=$msgid;
      }
    } else {
      $return=$msgid;
    }
  } else {
    $return=0;
  }
  return $return;
}



function new_thread($thread_info){ //�������� ������ ����� �� ������ ������
  mysql_query("delete from `tmp` where msgid=\"".$thread_info['thread']."\";");
  mysql_query("update `messages` set thread=\"".$thread_info['thread']."\", inthread=\"0\", level=\"0\" where msgid=\"".$thread_info['thread']."\" and area=\"".$thread_info['area']."\";");
  $thread_info['num']=1;
  return $thread_info;
}

function add_to_thread($thread_info,$msgid,$reply,$level,$inthread){ //���������� ������ � ��� ������������� �����
  //�������� level � inthread:
  // �������� ������ �������� ��������� ( � ������� reply ����� ��).
  $result=mysql_query("select level, max(inthread) as inthread from `tmp` where reply='$reply' and thread=\"".$thread_info['thread']."\" and area=\"".$thread_info['area']."\" group by reply;");
  if (mysql_num_rows($result)){ // ���� �������� ��������� ����
    $row=mysql_fetch_object($result);
    // level ����� �� ���, inthread - ������������+1
    $level=$row->level;
    $inthread=$row->inthread+1;
  } else { //���� �������� ��������� ���, �� ���� ������ ������������
  //����� level � inthread ��������, ����������� �� �� 1.
    $level++;
    $inthread++;
  }
  // "��������" ���� ��� ��������� �� �����, � ������� inthread ������ ��� ����� ������.
  mysql_query("update `messages` set inthread=inthread+1 where area=\"".$thread_info['area']."\" and thread=\"".$thread_info['thread']."\" and inthread >= \"$inthread\";");
  // ���������� level, inthread, thread ��� ������ ���������
  mysql_query("update `messages` set thread=\"".$thread_info['thread']."\", inthread=\"$inthread\", level=\"$level\" where msgid=\"".$msgid."\" and area=\"".$thread_info['area']."\";");
  // ��������� ��� ����� ��������� �� tmp
  mysql_query("delete from `tmp` where msgid=\"$msgid\";");
  // ��������, ������� ������ � ����� ���������
  $row=mysql_fetch_object(mysql_query("select num from `threads` where area=\"".$thread_info['area']."\" and thread=\"".$thread_info['thread']."\""));
  $thread_info['num']=$row->num + 1;
  return $thread_info;
}




function set_thread($thread_info,$msgid=0,$level=0){
  if (!$msgid){ $msgid=$thread_info['thread']; }
  mysql_query("delete from `tmp` where msgid=\"$msgid\";");
  mysql_query("update `messages` set thread=\"".$thread_info['thread']."\", inthread=\"".$thread_info['num']."\", level=\"$level\" where msgid=\"$msgid\" and area=\"".$thread_info['area']."\";");
  $thread_info['num']++;
  if ($level) { //������ ���������, ��� ������ �������� ������ ������. � � ���� ������ ����� ��� ����� ���� ��������� ������.
                // ��� ��� ����� ����� ������������� ������� �����, ������������ �� ���� �����, � ������� level>0
    mysql_query("delete from thread where area=\"".$thread_info['area']."\" and thread=\"$msgid\";");
  }
  $result=mysql_query("select msgid,recieved,date,fromaddr,fromname,hash,subject from `tmp` where reply=\"$msgid\" order by recieved;");
  while ($row=mysql_fetch_object($result)){
    if (match_text($row->subject,$thread_info['subject'])){
      if ($row->recieved > $thread_info['lastupdate']){ 
        $thread_info['lastupdate']=$row->recieved; 
        $thread_info['last_author']=$row->fromname; 
        $thread_info['last_author_address']=$row->fromaddr; 
        $thread_info['last_author_date']=$row->date; 
        $thread_info['last_hash']=$row->hash; 
      }
      $thread_info=set_thread($thread_info,$row->msgid,$level+1);
    }else{
      $new_thread_info = array ( 
	'area'			=> $thread_info['area'],
	'thread'		=> $row->msgid,
	'subject'		=> $row->subject,
	'author'		=> $row->fromname,
	'author_address'	=> $row->fromaddr,
	'author_date'		=> $row->date,
	'last_author'		=> $row->fromname,
	'last_author_address'	=> $row->fromaddr,
	'last_author_date'	=> $row->date,
	'num'			=> 0,
	'lastupdate'		=> $row->recieved,
	'last_hash'		=> $row->hash
	);
      $new_thread_info=set_thread($new_thread_info);
      save_thread($new_thread_info);
    }
  }
  return $thread_info;
}

function match_text($str1,$str2){
  $str1=preg_replace('/^Re/','',$str1);
  $str2=preg_replace('/^Re/','',$str2);
  $str1=preg_replace('/�/','H',$str1);
  $str2=preg_replace('/�/','H',$str2);
  $str1=preg_replace('/(:|\^| |[0-9]|\[.*\]|\(no subject\))/','',$str1);
  $str2=preg_replace('/(:|\^| |[0-9]|\[.*\]|\(no subject\))/','',$str2);
  if (levenshtein($str1,$str2, 1,10,1) < 20) {
    return 1;
  }else{
    print "|";
    return 0;
  }
  return 1;
}

function save_thread($thread_info){
    mysql_query("
	replace into `threads` set 
	  area=\"".$thread_info['area']."\", 
          thread=\"".$thread_info['thread']."\", 
          hash=\"".$thread_info['last_hash']."\", 
	  subject=\"".$thread_info['subject']."\",
	  author=\"".$thread_info['author']."\", 
	  author_address=\"".$thread_info['author_address']."\", 
          author_date=\"".$thread_info['author_date']."\", 
	  last_author=\"".$thread_info['last_author']."\",
	  last_author_date=\"".$thread_info['last_author_date']."\",
	  num=\"".$thread_info['num']."\", 
	  lastupdate=\"".$thread_info['lastupdate']."\";
    ");

}






?>
