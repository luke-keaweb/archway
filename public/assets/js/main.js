htmx.defineExtension('toggle-viewer', {
  onEvent: function (name, evt) {
    if (name === 'htmx:confirm') {
      const trigger = evt.detail.elt;     // what we clicked on
      // the table row containing the viewer
      const target = document.getElementById( trigger.dataset.id );

      // if the target element currently exists, remove it
      if (target) {
        target.remove();
        trigger.classList.remove('digital-toggled');   // remove the class on the trigger
        toggleText(trigger);
        return false;                                  // stop HTMX firing the request
      } else {
        trigger.classList.add('digital-toggled');
        toggleText(trigger);
        return true;                        // proceed as usual
      }

    } else if (name === 'htmx:configRequest') {

      const closestTr = evt.detail.elt.closest('tr');
      
      if (closestTr) {
        const tdCount = closestTr.querySelectorAll('td').length;
        // Append the colspan parameter to the request URL
        evt.detail.path = evt.detail.path + '?colspan=' + tdCount;
      }
      return true;
    }
  }
});


htmx.onLoad( function(content) {
  // this is fired when the page loads and when new content is inserted
  LoadEventListeners(content);
});




// HTMX fires this function when the page loads and when new content is inserted
function LoadEventListeners(content){
  
  // sort any tables that need to be sortable
  content.querySelectorAll('table').forEach(function(table) {
    if (table.querySelector('thead')) {
      tab_me = new SortableTable(table);
    }
  });

  // toggle highlighting of keywords
  content.querySelectorAll('span.toggle_highlight').forEach(function(span) {
    span.addEventListener('click', function() {
      content.querySelectorAll('.highlight').forEach(function(highlight) {
        highlight.classList.toggle('always_highlight');
      });
    });
  });
  
  // show/hide when toggling details / summary, if class is toggle_long_text
  const detailsElements = document.querySelectorAll('details.toggle_long_text');
  detailsElements.forEach(element => {
    element.addEventListener('toggle', function() {
      toggleText(element);
    });
  });

  // show technical info
  content.querySelectorAll('span.technical_info').forEach(function(span) {
    span.addEventListener('click', function() {
      const nextDiv = span.nextElementSibling;
      if (nextDiv && nextDiv.classList.contains('technical_info')) {
        nextDiv.style.display = (nextDiv.style.display === 'none' || nextDiv.style.display === '') ? 'block' : 'none';
      }
    });
  });

  // remove empty fields for cleaner URLs
  // content.addEventListener("submit", removeEmptyFields);

  // submit the form when we choose a facet to filter by
  content.querySelectorAll('.facet_select, .facet_chosen').forEach(function(el) {
    el.addEventListener('change', function() {
      const form = el.closest('form');
      if (form) {
        removeEmptyFields(form, el.name);
        htmx.trigger(form, 'submit');
      }
    });
  });
  
  // New code for handling clicks on submit buttons
  content.querySelectorAll('form input[type="submit"], form button').forEach(function(button) {
    button.addEventListener('click', function(event) {
      // console.log('Button clicked!');
      event.preventDefault();  // Prevent the default form submission behavior
      const form = button.closest('form');
      if (form) {
        removeEmptyFields(form);
        htmx.trigger(form, 'submit');
      }
    });
  });
  
  
  // trigger lightgallery on anything needs it
  content.querySelectorAll('.lightgallery').forEach(function(gallery) {
  
    lightGallery(gallery, {
      plugins: [ lgThumbnail, lgRotate, lgZoom ],
      licenseKey: '3014-9672-563-9691',
      speed: 200,
      thumbnail: true,
      // Rotate options
      flipHorizontal: false,
      flipVertical: false,
      // Zoom options
      actualSize: false,
      showZoomInOutIcons: true,
      mobileSettings: {
        download: false,
      }
    });
    
  });
  
  // trigger PDF embed lightgallery on anything needs it
  content.querySelectorAll('.lightgallery-pdf').forEach(function(gallery) {
    
    lightGallery(gallery, {
      licenseKey: '3014-9672-563-9691',
      selector: 'this',
      speed: 200,
      // Rotate options
      flipHorizontal: false,
      flipVertical: false,
    });
    
  });
  
} // end of LoadEventListeners function





function removeEmptyFields(form, exclude='') {
  // Loop through each input element in the form
  form.querySelectorAll('input, select, textarea').forEach(element => {
    // console.log(element.name + ' has value [' + element.value + '] ...');
      if (!element.value && element.name != exclude) {
        // console.log('Blanking ' + element.name);
        element.removeAttribute('name');
      }
    });
}


function toggleText(label) {
  const togglePairs = [
    ['Show', 'Hide'], ['Hide', 'Show'],
    ['More', 'Less'], ['Less', 'More']
  ];

  let text = label.textContent;

  togglePairs.forEach(pair => {
    const [from, to] = pair;
    if (text.indexOf(from) > -1 && text.indexOf(from) < 5) {
      label.innerHTML = label.innerHTML.replace(from, to);
    }
  });

  return true;
}