/****************************************************************
 * Slider
 *   
 * Slider000118
 * by Christiaan Hofman, January 2000
 *   
 * You may use or modify this code provided that this copyright notice
 * appears on all copies.
 *   
 *  Constructor:
 *    var slider = new Slider( [name] );
 *   
 *  Defining Methods
 *    slider.writeSlider();
 *    slider.placeSlider( [imgName, layer] );
 * 
 *  Control Methods:
 *    slider.getValue();
 *    slider.setValue( [value] );
 *
 *  Event Handlers:
 *    slider.onmouseover( e, slider );
 *    slider.onmouseout( e, slider );
 *    slider.onmousedown( e, slider );
 *    slider.onmouseup( e, slider );
 *    slider.onslide( e, slider );
 *    slider.onchange( e, slider );
 *    slider.onclick( e, slider );
 * 
 *  Default Initial Settings:   
 * 
 *    slider.leftValue = 0;
 *    slider.rightValue = 1;
 *    slider.defaultValue = 0;
 *    slider.offsetX = 1;
 *    slider.offsetY = 1;
 *    slider.maxSlide = 258;
 *    slider.buttonWidth = 40;
 *    slider.buttonHeight = 26;
 *    slider.railImg = "sliderbg.gif";
 *    slider.buttonImg = "sliderbutton.gif";
 *    slider.buttonHiliteImg = "sliderhibutton.gif";
 *    slider.imgPath = "";
 *    slider.orientation = "h";
 * 
 ****************************************************************
 */

// Constructor

function Slider(name) {
    this.leftValue = 0;
    this.rightValue = 1;
    this.defaultValue = 0;
    this.offsetX = 0;
    this.offsetY = 0;
    this.maxSlide = 172;
    this.buttonWidth = 15;
    this.buttonHeight = 28;
    this.buttonImg = "sliderbutton.gif";
    this.buttonHiliteImg = "sliderhibutton.gif";
    this.imgPath = "";
    this.orientation = "h";
    
    this.writeSlider = Slider.writeSlider;
    this.placeSlider = Slider.placeSlider;
    this.makeEventHandler = Slider.makeEventHandler;
    this.isPrototype = Slider.isPrototype;
    this.getValue = Slider.getValue;
    this.setValue = Slider.setValue;
     
    this.MouseOver = Slider.MouseOver;
    this.MouseOut = Slider.MouseOut;
    this.MouseDown = Slider.MouseDown;
    this.MouseUp = Slider.MouseUp;
    this.MouseSlide = Slider.MouseSlide;

    this.onmouseover = null;
    this.onmouseout = null;
    this.onmousedown = null;
    this.onmouseup = null;
    this.onslide = null;
    this.onchange = null;
    this.onclick = null;

    if (!window.sliders)  window.sliders = new Array();
    this.name = name || "slider"+window.sliders.length;
    window.sliders[window.sliders.length] = this;
    window.sliders[this.name] = this;
    if (!window.sliderDrag)  window.sliderDrag = new Object();
}

// method write the button DIV

Slider.writeSlider = function () {
    var proto = this.prototype || this;

    // create images for the prototype, if not alrady set
    if (!proto.loImg || !proto.hiImg) {
        proto.loImg = new Image(proto.buttonWidth,proto.buttonHeight);
        proto.hiImg = new Image(proto.buttonWidth,proto.buttonHeight);
        proto.loImg.src = proto.imgPath + proto.buttonImg;
        proto.hiImg.src = proto.imgPath + (proto.buttonHiliteImg || proto.buttonImg);
        if (proto.buttonOverImg) {
            proto.ovImg = new Image(proto.buttonWidth,proto.buttonHeight);
            proto.ovImg.src = proto.imgPath + proto.buttonOverImg;
        }
    }
    // set the properties according to the prototype
    if (proto != this) {
        this.loImg = proto.loImg;
        this.hiImg = proto.hiImg;
        if (proto.ovImg)  this.ovImg = proto.ovImg;
        this.orientation = proto.orientation;
        this.maxSlide = proto.maxSlide;
    }

    // style for the slider button
    var style = '<STYLE TYPE="text/css"><!--\n' +
        '#'+this.name+'Button {visibility:hidden; position:absolute; width:'+ proto.buttonWidth +'px; height:'+ proto.buttonHeight +'px; z-index:1; }\n' +
        '--></STYLE>';

    // html for the button div
    var content = '<DIV ID="'+this.name+'Button">'+
        '<IMG ID="'+this.name+'ButtonImg" SRC="'+proto.loImg.src+'" WIDTH='+proto.buttonWidth+' HEIGHT='+proto.buttonHeight+'>'+
        '</DIV>';

    // write the button style and content in the document
    if (document.getElementById || document.layers || document.all) {
        document.writeln(style);
        document.writeln(content);
    }

    // set button properties and mouse event handlers
    if (document.layers) {
        // NS4 code
        this.button = document.layers[this.name+"Button"];
        this.button.img = this.button.document.images[0];
        this.button.clip.width = proto.buttonWidth;
        this.button.clip.height = proto.buttonHeight;
        this.button.captureEvents(Event.MOUSEOVER|Event.MOUSEDOWN|Event.MOUSEOUT);
        this.button.onmousedown = this.MouseDown;
        this.button.onmouseout = this.MouseOut;
        this.button.onmouseover = this.MouseOver;
     } else if (document.all) { 
        // IE code
        this.button = document.all[this.name+"Button"];
        this.button.img = document.all[this.name+"ButtonImg"];
        this.button.style.pixelWidth = proto.buttonWidth;
        this.button.style.pixelHeight = proto.buttonHeight;
        this.button.onmousedown = this.MouseDown;
        this.button.onmouseout = this.MouseOut;
        this.button.onmouseover = this.MouseOver;
    } else if (document.getElementById) { 
        // W3DOM code
        this.button = document.getElementById(this.name+"Button");
        this.button.img = document.getElementById(this.name+"ButtonImg");
        this.button.style.width = proto.buttonWidth +"px";
        this.button.style.height = proto.buttonHeight +"px";
        this.button.addEventListener("mousedown",this.MouseDown,false);
        this.button.addEventListener("mouseout",this.MouseOut,false);
        this.button.addEventListener("mouseover",this.MouseOver,false);
    }
    // set event handlers as functions
    this.onmouseover = this.makeEventHandler(this.onmouseover);
    this.onmouseout  = this.makeEventHandler(this.onmouseout);
    this.onmousedown = this.makeEventHandler(this.onmousedown);
    this.onmouseup   = this.makeEventHandler(this.onmouseup);
    this.onslide     = this.makeEventHandler(this.onslide);
    this.onchange    = this.makeEventHandler(this.onchange);
    this.onclick     = this.makeEventHandler(this.onclick);
    // tell button who we are
    this.button.slider = this;
    // from now on button refers to the style object in IE and W3DOM
    if (document.all || document.getElementById) this.button = this.button.style;
}

// method to put the slider button in place

Slider.placeSlider = function (imgName,layer) {
    var proto = this.prototype || this;

    // for NS4 refer to document of layer containing slider rail
    var doc = (document.layers && layer)? ((typeof(layer)=='string')? document.layers[layer].document : layer.document) : document;
    // set name or default name
    imgName = imgName || this.name+'RailImg';
    // find the rail image
    this.rail = (typeof(imgName)=='string')? doc.images[imgName] : imgName;
    // offset w.r.t rail
    var x = proto.offsetX;
    var y = proto.offsetY;
    // add global position of rail in global document to the offset
    if (document.layers) {
        // NS4 code
        x += (typeof(this.rail.pageX)=='number')? this.rail.pageX : this.rail.x;
        y += (typeof(this.rail.pageY)=='number')? this.rail.pageY : this.rail.y;
        if (layer) {
            x += layer.pageX;
            y += layer.pageY;
        }
    } else if (document.all || document.getElementById) {
        // IE and W3DOM code, add all ancestors
        var parent = this.rail;
        while (parent) {
            x += parent.offsetLeft;
            y += parent.offsetTop;
            parent = parent.offsetParent;
        }
    }
    // set position of button
    if (document.layers) {
        this.button.left = x;
        this.button.top = y;
    } else if (document.all) {
        this.button.pixelLeft = x;
        this.button.pixelTop = y;
    } else if (document.getElementById) { 
        this.button.left = x+"px";
        this.button.top = y+"px";
    }
    // offset is remembered for later sliding
    this.offset = (proto.orientation == "v")? y : x;
    // put button in default position and make visible
    this.setValue(this.defaultValue,true);
    this.button.visibility = "inherit";
}

// makes slider a prototype for all previously defined sliders

Slider.isPrototype = function () {
    for (var i=0; i<window.sliders.length; i++)  
        window.sliders[i].prototype = window.sliders[i].prototype || this;
}

// mouseover handler of the button, only calls handler of slider

Slider.MouseOver = function (e) {
    if (this.slider.ovImg)  this.img.src = this.slider.ovImg.src;   
    if (this.slider.onmouseover)  this.slider.onmouseover(e);
}

// mouseout handler of the button, only calls handler of slider

Slider.MouseOut = function (e) {
    if (this.slider.ovImg)  this.img.src = this.slider.loImg.src;   
    if (this.slider.onmouseout)  this.slider.onmouseout(e);
}

// mousedown handler of the button

Slider.MouseDown = function (e) {
    var slider = this.slider;
    // remember me
    window.sliderDrag.dragLayer = this;
    window.sliderDrag.dragged = false;
    // set starting offset of event
    if (document.layers) {
        // NS4 code
        if (e.which > 1)  return true;
        window.sliderDrag.offX = e.pageX - this.left + slider.offset;
        window.sliderDrag.offY = e.pageY - this.top + slider.offset;
    } else if (document.all) {
        // IE code
        window.sliderDrag.offX  =  window.event.clientX - this.style.pixelLeft + slider.offset;
        window.sliderDrag.offY  =  window.event.clientY - this.style.pixelTop + slider.offset;
        window.event.cancelBubble = true;
    } else if (document.getElementById) {
        // W3DOM code
        if (e.button > 0)  return true;
        window.sliderDrag.offX  =  e.pageX - parseInt(this.style.left) + slider.offset;
        window.sliderDrag.offY  =  e.pageY - parseInt(this.style.top) + slider.offset;
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
    }
    // document handles move and up events
    document.onmousemove = slider.MouseSlide;
    document.onmouseup = slider.MouseUp;
    // capture events in NS4
    if (document.captureEvents) document.captureEvents(Event.MOUSEMOVE|Event.MOUSEUP);
    // show hilite img
    this.img.src = slider.hiImg.src;
    // call event handler of slider
    if (slider.onmousedown)  slider.onmousedown(e);
    return false;
}

// mouseup handler of the button

Slider.MouseUp = function (e) {
    // button and slider that was draged
    var l = window.sliderDrag.dragLayer;
    var slider = l.slider;
    // cancel move and up event handlers of document
    document.onmousemove = null;
    document.onmouseup = null;
    // release them in NS4
    if (document.releaseEvents) document.releaseEvents(Event.MOUSEMOVE|Event.MOUSEUP);
    window.sliderDrag.dragLayer = null;
    // show normal image
    l.img.src = slider.loImg.src;
    // cal event handlers of slider
    if (slider.onchange)  slider.onchange(e);
    if (slider.onmouseup)  slider.onmouseup(e);
    if (!window.sliderDrag.dragged && slider.onclick) slider.onclick(e);
    return false;
}

// mousemove handler of the button for sliding

Slider.MouseSlide = function (e) {
    // button and slider to be draged
    var l = window.sliderDrag.dragLayer;
    var slider = l.slider;
    // we have dragged the slider; for click
    window.sliderDrag.dragged = true;
    // move slider. Note event-off=0 corresponds to left/up position
    if (document.layers) {
       // NS4 code 
       if (slider.orientation == "h")
            l.left = Math.max(Math.min(e.pageX - window.sliderDrag.offX,slider.maxSlide),0) + slider.offset;
        else  l.top = Math.max(Math.min(e.pageY - window.sliderDrag.offY,slider.maxSlide),0) + slider.offset;
    } else if (document.all) {
        // IE code
        if (slider.orientation == "h")
            l.style.pixelLeft = document.body.scrollLeft + document.documentElement.scrollLeft + Math.max(Math.min(window.event.clientX - window.sliderDrag.offX,slider.maxSlide),0) + slider.offset;
        else  l.style.pixelTop = document.body.scrollTop + document.documentElement.scrollTop + Math.max(Math.min(window.event.clientY - window.sliderDrag.offY,slider.maxSlide),0) + slider.offset;
        window.event.cancelBubble = true;
    } else if (document.getElementById) {
        // W3DOM code
        if (slider.orientation == "h") 
            l.style.left = (Math.max(Math.min(e.pageX - window.sliderDrag.offX,slider.maxSlide),0) + slider.offset) +"px";
        else  l.style.top = (Math.max(Math.min(e.pageY - window.sliderDrag.offY,slider.maxSlide),0) + slider.offset) +"px";
       if (e.cancelable) e.preventDefault();
       e.stopPropagation();
    }
    // call slider event handlers
    if (slider.onchange)  slider.onchange(e);
    if (slider.onslide)  slider.onslide(e);
    return false;
}

// calculate the value of the slider from position

Slider.getValue = function () {
    var pos = (this.orientation == "h")? parseInt(this.button.left) : parseInt(this.button.top);
    return  this.leftValue + (this.rightValue-this.leftValue) * (pos-this.offset) / this.maxSlide;
}

// set the position of the slider from a value

Slider.setValue = function (value,ignore) {
    if (typeof(value) == "string")  value = parseFloat(value);
    if (isNaN(value))  value = this.defaultValue;
    // set within min/max bounds
    var rangeValue = (this.rightValue >= this.leftValue)? 
        Math.min(Math.max(value,this.leftValue),this.rightValue) - this.leftValue : 
        Math.max(Math.min(value,this.leftValue),this.rightValue) - this.leftValue;
    // move button to calculated position
    if (document.layers) {
        // NS4 code
        if (this.orientation == "h") 
            this.button.left = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset;
        else  this.button.top = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset;
    } else if (document.all) {
        // IE code
        if (this.orientation == "h") 
            this.button.pixelLeft = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset;
        else  this.button.pixelTopt = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset;
    } else if (document.getElementById) {
        // W3DOM code
        if (this.orientation == "h") 
            this.button.left = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset +"px";
        else  this.button.top = this.maxSlide * rangeValue / (this.rightValue-this.leftValue) + this.offset +"px";
    }
    // call slider event handler, unless ignore is true
    if (this.onchange && (!ignore))  this.onchange(null);
}

// make an event handler, ensuring that it is a function

Slider.makeEventHandler = function (f) {
    return (typeof(f) == "string")? new Function('e',f) : ((typeof(f) == "function")? f : null);
}

// return a value as a string with a fixed number of decimals

function toDecimals(val,n) {
    if (isNaN(n)) return val;
    if (n<=0) return Math.round(val);
    for (var m=0; m<n; m++)  val *= 10;
    val = Math.round(val);
    valstr = val.toString();
    len = valstr.length;
    if (len>n)
        valstr = valstr.substring(0,len-n) +"."+ valstr.substring(len-n,len);
    else {
        while (valstr.length<n) valstr = "0"+valstr;
        valstr = "0."+valstr;
    }
    return valstr;
}
