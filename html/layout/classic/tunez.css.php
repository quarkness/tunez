<?php
header('Content-Type: text/css; charset=ISO-8859-1');
?>
body  {
  color : #333;
  background-color : white;
  margin : 20px 20px 20px 20px;
  padding : 0;
  font : 11px verdana, arial, helvetica, sans-serif;
}

h1 {
  margin : 0 0 25px 0;
  padding : 0;
  font-size : 32px;
  font-weight : 900;
  color : #ccc;
}

h2 {
  font : bold 12px/14px verdana, arial, helvetica, sans-serif;
  margin : 0 0 10px;
  padding : 0;
}

h3 {
  font : bold 12px/14px verdana, arial, helvetica, sans-serif;
  margin : 0 0 10px;
  padding : 0;
}

h4 {
  font : bold 9px/11px verdana, arial, helvetica, sans-serif;
  color: #771E00;
  margin : 10px 0 7px;
  padding : 0;
}

h4:before
{
  content: ':: ';
}

p {
  font : 11px/20px verdana, arial, helvetica, sans-serif;
  margin : 0 0 16px;
  padding : 0;
}

p.navText {
	margin-top: 16px;
	text-align: center
}

th {
  font : bold 11px/20px verdana, arial, helvetica, sans-serif;
  background-color : #ccc;
  color : #000000;

}

td {
  font : 11px verdana, arial, helvetica, sans-serif;
  background-color : #eee;
  text-align: left;

}

a {
  color : #09c;
  font-size : 11px;
  font-family : verdana, arial, helvetica, sans-serif;
  font-weight : 600;
  text-decoration : none;
}

a:link {
  color : #09c;
}

a:visited {
  color : #07a;
}

a:hover {
  /*  background-color : #eee; */
  text-decoration : underline;

}

#toggleLink {
  color : #09c;
  font-size : 11px;
  font-family : verdana, arial, helvetica, sans-serif;
  font-weight : 600;
  text-decoration : none;
}

<?php
if(stristr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
{
?>
.content {
  width : auto;
  margin : 0 270px 20px 190px;
  border : 1px solid black;
  padding : 10px;
}
<?php
}
else
{
?>
.content {
  width : auto;
  margin : 0 250px 20px 170px;
  border : 1px solid black;
  padding : 10px;
}

<?php
}
?>

.centered {
  margin : auto;
  text-align: center;
}

.formdiv {
  border-right : 1px solid #DADADA;
  border-bottom : 1px solid #DADADA;
  border-left : 1px solid #DEDEDE;
  border-top : 1px solid #DEDEDE;
  margin : 10px;
  padding : 15px;
}

#chartform {
  border-right : 1px solid #DADADA;
  border-bottom : 1px solid #DADADA;
  border-left : 1px solid #DEDEDE;
  border-top : 1px solid #DEDEDE;
  margin : 10px;
  padding : 15px;
  display: none;
  visibility: hidden;
}

#leftnav {
  position : absolute;
  width : 150px;
  top : 90px;
  left : 20px;
  border : 1px dashed black;
  background-color : #eee;
  padding : 10px;
}

#rightnav {
  position : absolute;
  width : 230px;
  top : 20px;
  right : 20px;
  border : 1px dashed black;
  background-color : #eee;
  padding : 10px;
}

#tunezimage {
  position : absolute;
  top : 0px;
  left : 0px;
}

#message {
  margin : 0 0 25px 0;
  border : 1px solid #666B76;
  background-color : #eee;
  padding : 10px;
}

body > #leftnav {
  width : 128px;
}

body > #rightnav {
  width : 208px;
}

.lyrics {

  font-size : 12px;
  font-family : courier;
  text-decoration : none;
}

.field {
  border-right : 1px solid #ffd795;
  border-bottom : 1px solid #ffd795;
  border-left : 1px solid #bf9755;
  border-top : 1px solid #bf9755;
  background-color : #ffd495;
  color : #cf8745;
  /* -moz-border-radius: 9px 9px 9px 9px; */
  padding:0px 2px 0px 5px ;
  text-align: center;
}

.radiobutton {
  background-color : #ffd495;
  color : #cf8745;
  margin : 0 0 0 25px;

}

.button {
  color : #AA5A10;
  border-right : 1px solid #bf9755;
  border-bottom : 1px solid #bf9755;
  border-left : 1px solid #ffd795;
  border-top : 1px solid #ffd795;
  background-color : #ffc485;
  margin : 5px 0 0 0;
  /* -moz-border-radius: 25px 25px 25px 25px; */
  padding:0px 5px 0px 5px ;

}

.dropdown {
  color : #AA5A10;
  border: 1px solid black;
  background-color : #ffc485;
  margin : 5px 0 0 0;
  padding:0px 0px 0px 5px ;
  font-size : 11px;
}

FORM {
  margin-top : 0;
  margin-bottom : 0;
}

