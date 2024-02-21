/**
*
*  Sortable HTML table
*  http://www.webtoolkit.info/
*
**/

function SortableTable(tableEl) {
 
	this.tbody = tableEl.getElementsByTagName('tbody');
	this.thead = tableEl.getElementsByTagName('thead');
 
	this.getInnerText = function(el) {
    if (!el) return false;
		if (typeof(el.textContent) != 'undefined') return this.onlySummary(el);
		if (typeof(el.innerText) != 'undefined') return el.innerText;
		if (typeof(el.innerHTML) == 'string') return el.innerHTML.replace(/<[^<>]+>/g,'');
    return false;
	}
  
  // if the element contains a <summary> field, return just the content of that
  this.onlySummary = function(el) {
    const summaryElement = el.querySelector('summary');
    
    if (summaryElement)
      return summaryElement.textContent;
    else
      return el.textContent;
    
  }
 
	this.getParent = function(el, pTagName) {
		if (el == null) return null;
		else if (el.nodeType == 1 && el.tagName.toLowerCase() == pTagName.toLowerCase())
			return el;
		else
			return this.getParent(el.parentNode, pTagName);
	}
 
	this.sort = function(cell) {
 
    // stop here if we have no row to sort 
    if ( !(this.tbody[0].rows[1]) ) return;
 
	  var column = cell.cellIndex;
	  var itm = this.getInnerText(this.tbody[0].rows[1].cells[column]);
		var sortfn = this.sortCaseInsensitive;
    
    // console.log('String to sort:' + itm );
 
    // does the first row cell contain a date in format mm-dd-yyyy ?
		if (itm.match(/\d\d[-]+\d\d[-]+\d\d\d\d/)) sortfn = this.sortDate; // date format mm-dd-yyyy
    
    if (IsNumeric(itm)) sortfn = this.sortNumeric;
    
    // does the first row cell contain a string-type date?
    if ( isDate(itm) ) sortfn = this.sortStringDate; // date format eg 18 Mar 2019

    // if ( isDate(itm) )
    //   console.log('This is a date column!');

		this.sortColumnIndex = column;
 
	    var newRows = new Array();
	    for (j = 0; j < this.tbody[0].rows.length; j++) {
			newRows[j] = this.tbody[0].rows[j];
		}
 
		newRows.sort(sortfn);
 
		if (cell.getAttribute("sortdir") == 'down') {
			newRows.reverse();
			cell.setAttribute('sortdir','up');
		} else {
			cell.setAttribute('sortdir','down');
		}
 
		for (i=0;i<newRows.length;i++) {
			this.tbody[0].appendChild(newRows[i]);
		}
 
	}
 
	this.sortCaseInsensitive = function(a,b) {
		aa = thisObject.getInnerText(a.cells[thisObject.sortColumnIndex]);
		bb = thisObject.getInnerText(b.cells[thisObject.sortColumnIndex]);
    
    // check if the elements actually exist
    if (!aa || !bb) return false;
    
    aa = aa.toLowerCase();
    bb = bb.toLowerCase();
    
		if (aa==bb) return 0;
		if (aa<bb) return -1;
		return 1;
	}
 
	this.sortDate = function(a,b) {
    
    if ( a.cells[thisObject.sortColumnIndex] === undefined )
      return 0;
    if ( b.cells[thisObject.sortColumnIndex] === undefined )
      return 0;
    
		aa = thisObject.getInnerText(a.cells[thisObject.sortColumnIndex]);
		bb = thisObject.getInnerText(b.cells[thisObject.sortColumnIndex]);
		date1 = aa.substr(6,4)+aa.substr(3,2)+aa.substr(0,2);
		date2 = bb.substr(6,4)+bb.substr(3,2)+bb.substr(0,2);
		if (date1==date2) return 0;
		if (date1<date2) return -1;
		return 1;
	}
  
  this.sortStringDate = function(a,b) {   
    
    // if a cell doesn't exist, ignore it (eg if there is a colspan in the table)
    if ( a.cells[thisObject.sortColumnIndex] === undefined )
      return 0;
    if ( b.cells[thisObject.sortColumnIndex] === undefined )
      return 0;
     
    aa = thisObject.getInnerText(a.cells[thisObject.sortColumnIndex]);
		bb = thisObject.getInnerText(b.cells[thisObject.sortColumnIndex]);

    // remove magnifying glasses
    // aa = aa.replace('🔍', '').trim();
    // bb = bb.replace('🔍', '').trim();
        
		date1 = new Date( aa ).getTime();
		date2 = new Date( bb ).getTime();
    
		if (date1==date2) return 0;
		if (date1<date2) return -1;
		return 1;
  }
 
	this.sortNumeric = function(a,b) {

		aa = thisObject.getInnerText(a.cells[thisObject.sortColumnIndex]);
		aa = 	aa.replace(/\$/g,'')
		aa = parseFloat(aa);

		if (isNaN(aa)) aa = 0;
		
		bb = thisObject.getInnerText(b.cells[thisObject.sortColumnIndex]);
		bb = 	bb.replace(/\$/g,'')
		bb = parseFloat(bb);
		
		if (isNaN(bb)) bb = 0;
		return aa-bb;
	}
 
	// define variables
	var thisObject = this;
	var sortSection = this.thead;
 
	// constructor actions
  
  // stop here if we have no table body to sort 
	if (!(this.tbody) ) return;
  if (!(this.tbody) && this.tbody[0].rows && this.tbody[0].rows.length > 0) return;
 
	if (sortSection && sortSection[0].rows && sortSection[0].rows.length > 0) {
		var sortRow = sortSection[0].rows[0];
	} else {
		return;
	}
 
	for (var i=0; i<sortRow.cells.length; i++) {
		sortRow.cells[i].sTable = this;
		sortRow.cells[i].onclick = function () {
			this.sTable.sort(this);
			return false;
		}
	}
	 
}



function IsNumeric(strString) {
   //  check for valid numeric strings	

   var strValidChars = "$0123456789.-";
   var strChar;
   var blnResult = true;

   if (strString.length == 0) return false;

   //  test strString consists of valid characters listed above
   for (i = 0; i < strString.length && blnResult == true; i++)
      {
      strChar = strString.charAt(i);
      if (strValidChars.indexOf(strChar) == -1)
         {
         blnResult = false;
         }
      }
   return blnResult;
}


function isDate( string ) {
    
    if( !isNaN(Date.parse(string)) )
        return true;
    
    return false;
    
} // end of isDate
