function hide_right() {
    document.getElementById("rightnav").visibility = false;
}
function subtractOne() {
    clock = document.getElementById("secondsLeft");
	if (clock == null) return;
    textObj = clock.firstChild;

    var newValue = new String(textObj.data);
	var sVal = newValue.split(":");
        
	if (sVal[1] <= 0) {
	    --sVal[0];
		sVal[1]=59;
	} else {
		--sVal[1];
	}

	if (sVal[1] <10) { 
        secPrint = "0" + sVal[1]; 
    } else { 
        secPrint = sVal[1]; 
    }
		
	textObj.data = sVal[0] + ":" + secPrint;

	newValue = (sVal[0]*60) + sVal[1];
	if (newValue > 0) {
        myTimer=setTimeout("subtractOne()",1000)
    }
    else {
        clearTimeout("myTimer")
    }
}

myTimer=setTimeout("subtractOne()",1000)
