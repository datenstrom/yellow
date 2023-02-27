// global variables
const doc = document.documentElement;
const inline = ":inline";
// variables read from your hugo configuration
const parentURL = '{{ absURL "" }}';
let showImagePosition = "{{ .Site.Params.figurePositionShow }}";

const showImagePositionLabel = '{{ .Site.Params.figurePositionLabel }}';

function isObj(obj) {
  return (obj && typeof obj === 'object' && obj !== null) ? true : false;
}

function createEl(element = 'div') {
  return document.createElement(element);
}

function elem(selector, parent = document){
  let elem = parent.querySelector(selector);
  return elem != false ? elem : false;
}

function elems(selector, parent = document) {
  let elems = parent.querySelectorAll(selector);
  return elems.length ? elems : false;
}

function pushClass(el, targetClass) {
  if (isObj(el) && targetClass) {
    elClass = el.classList;
    elClass.contains(targetClass) ? false : elClass.add(targetClass);
  }
}

function hasClasses(el) {
  if(isObj(el)) {
    const classes = el.classList;
    return classes.length
  }
}

(function markInlineCodeTags(){
  const codeBlocks = elems('code');
  if(codeBlocks) {
    codeBlocks.forEach(function(codeBlock){
          // Fix for orgmode inline code, leave 'verbatim' alone as well
          containsClass(codeBlock, 'verbatim') ? pushClass(codeBlock, 'noClass') :false;
      hasClasses(codeBlock) ? false: pushClass(codeBlock, 'noClass');
    });
  }
})();

function deleteClass(el, targetClass) {
  if (isObj(el) && targetClass) {
    elClass = el.classList;
    elClass.contains(targetClass) ? elClass.remove(targetClass) : false;
  }
}

function modifyClass(el, targetClass) {
  if (isObj(el) && targetClass) {
    elClass = el.classList;
    elClass.contains(targetClass) ? elClass.remove(targetClass) : elClass.add(targetClass);
  }
}

function containsClass(el, targetClass) {
  if (isObj(el) && targetClass && el !== document ) {
    return el.classList.contains(targetClass) ? true : false;
  }
}

function elemAttribute(elem, attr, value = null) {
  if (value) {
    elem.setAttribute(attr, value);
  } else {
    value = elem.getAttribute(attr);
    return value ? value : false;
  }
}

function wrapEl(el, wrapper) {
  el.parentNode.insertBefore(wrapper, el);
  wrapper.appendChild(el);
}

function deleteChars(str, subs) {
  let newStr = str;
  if (Array.isArray(subs)) {
    for (let i = 0; i < subs.length; i++) {
      newStr = newStr.replace(subs[i], '');
    }
  } else {
    newStr = newStr.replace(subs, '');
  }
  return newStr;
}

function isBlank(str) {
  return (!str || str.trim().length === 0);
}

function isMatch(element, selectors) {
  if(isObj(element)) {
    if(selectors.isArray) {
      let matching = selectors.map(function(selector){
        return element.matches(selector)
      })
      return matching.includes(true);
    }
    return element.matches(selectors)
  }
}

function copyToClipboard(str) {
  let copy, selection, selected;
  copy = createEl('textarea');
  copy.value = str;
  copy.setAttribute('readonly', '');
  copy.style.position = 'absolute';
  copy.style.left = '-9999px';
  selection = document.getSelection();
  doc.appendChild(copy);
  // check if there is any selected content
  selected = selection.rangeCount > 0 ? selection.getRangeAt(0) : false;
  copy.select();
  document.execCommand('copy');
  doc.removeChild(copy);
  if (selected) { // if a selection existed before copying
    selection.removeAllRanges(); // unselect existing selection
    selection.addRange(selected); // restore the original selection
  }
}

const iconsPath = '{{ default "icons/" .Site.Params.iconsDir }}';
function loadSvg(file, parent, path = iconsPath) {
  const link = `${parentURL}${path}${file}.svg`;
  fetch(link)
  .then((response) => {
    return response.text();
  })
  .then((data) => {
    parent.innerHTML = data;
  });
}

function getMobileOperatingSystem() {
  let userAgent = navigator.userAgent || navigator.vendor || window.opera;

  if (/android/i.test(userAgent)) {
    return "Android";
  }

  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
    return "iOS";
  }

  return "unknown";
}

function horizontalSwipe(element, func, direction) {
  // call func if result of swipeDirection() 👇🏻 is equal to direction
  let touchstartX = 0;
  let touchendX = 0;
  let swipeDirection = null;

  function handleGesure() {
    return (touchendX + 50 < touchstartX) ? 'left' : (touchendX < touchstartX + 50) ? 'right' : false;
  }

  element.addEventListener('touchstart', e => {
    touchstartX = e.changedTouches[0].screenX
  });

  element.addEventListener('touchend', e => {
    touchendX = e.changedTouches[0].screenX
    swipeDirection = handleGesure()
    swipeDirection === direction ? func() : false;
  });

}

function parseBoolean(string) {
  let bool;
  string = string.trim().toLowerCase();
  switch (string) {
    case 'true':
      return true;
    case 'false':
      return false;
    default:
      return undefined;
  }
};

(function() {
  const bodyElement = elem('body');
  const platform = navigator.platform.toLowerCase();
  if(platform.includes("win")) {
    pushClass(bodyElement, 'windows');
  }
})();

// Theme color mode
(function toggleColorModes(){
    const light = 'lit';
    const dark = 'dim';
    const storageKey = 'colorMode';
    const key = '--color-mode';
    const data = 'data-mode';
    const bank = window.localStorage;
    
    function currentMode() {
      let acceptableChars = light + dark;
      acceptableChars = [...acceptableChars];
      let mode = getComputedStyle(doc).getPropertyValue(key).replace(/\"/g, '').trim();
      
      mode = [...mode].filter(function(letter){
        return acceptableChars.includes(letter);
      });
      
      return mode.join('');
    }
    
    function changeMode(isDarkMode) {
      if(isDarkMode) {
        bank.setItem(storageKey, light)
        elemAttribute(doc, data, light);
      } else {
        bank.setItem(storageKey, dark);
        elemAttribute(doc, data, dark);
      }
    }
    
    function setUserColorMode(mode = false) {
      const isDarkMode = currentMode() == dark;
      const storedMode = bank.getItem(storageKey);
      if(storedMode) {
        if(mode) {
          changeMode(isDarkMode);
        } else {
          elemAttribute(doc, data, storedMode);
        }
      } else {
        if(mode === true) {
          changeMode(isDarkMode) 
        }
      }
    }
    
    setUserColorMode();
    
    doc.addEventListener('click', function(event) {
      let target = event.target;
      let modeClass = 'color_choice';
      let animateClass = 'color_animate';
      let isModeToggle = containsClass(target, modeClass);
      if(isModeToggle) {
        pushClass(target, animateClass);
        setUserColorMode(true);
      }
    });
})();

// Nav toggle
(function navToggle() {
    doc.addEventListener('click', function(event){
      const target = event.target;
      const open = 'jsopen';
      const navCloseIconClass = '.nav_close';
      const navClose = elem(navCloseIconClass);
      const isNavToggle = target.matches(navCloseIconClass) || target.closest(navCloseIconClass);
      const harmburgerIcon = navClose.firstElementChild.firstElementChild;
      if(isNavToggle) {
        event.preventDefault();
        modifyClass(doc, open);
        modifyClass(harmburgerIcon, 'isopen');
      }
      
      if(!target.closest('.nav') && elem(`.${open}`)) {
        modifyClass(doc, open);
        let navIsOpen = containsClass(doc, open);
        !navIsOpen  ? modifyClass(harmburgerIcon, 'isopen') : false;
      }
      
      const navItem = 'nav_item';
      const navSub = 'nav_sub';
      const showSub = 'nav_open';
      const isNavItem = target.matches(`.${navItem}`);
      const isNavItemIcon = target.closest(`.${navItem}`)
      
      if(isNavItem || isNavItemIcon) {
        const thisItem = isNavItem ? target : isNavItemIcon;
        const hasNext = thisItem.nextElementSibling
        const hasSubNav = hasNext ? hasNext.matches(`.${navSub}`) : null;
        if (hasSubNav) {
          event.preventDefault();
          Array.from(thisItem.parentNode.parentNode.children).forEach(function(item){
            const targetItem = item.firstElementChild;
            targetItem != thisItem ? deleteClass(targetItem, showSub) : false;
          });
          modifyClass(thisItem, showSub);
        }
      }
    });
})();

function isMobileDevice() {
  const agent = navigator.userAgent.toLowerCase();
  const isMobile = agent.includes('android') || agent.includes('iphone');
  return  isMobile;
};

(function ifiOS(){
  // modify backto top button
  const backToTopButton = elem('.to_top');
  const thisOS = getMobileOperatingSystem();
  const ios = 'ios';
  if(backToTopButton && thisOS === 'iOS') {
    pushClass(backToTopButton, ios);
  }
  // precisely position back to top button on large screens
  const buttonParentWidth = backToTopButton.parentNode.offsetWidth;
  const docWidth = doc.offsetWidth;
  let leftOffset = (docWidth - buttonParentWidth) / 2;
  const buttonWidth = backToTopButton.offsetWidth;
  leftOffset = leftOffset + buttonParentWidth - buttonWidth;
  if(!isMobileDevice()){
    backToTopButton.style.left = `${leftOffset}px`;
  }
})();
