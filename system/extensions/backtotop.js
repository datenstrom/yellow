// Backtotop extension, https://github.com/GiovanniSalmeri/yellow-backtotop

"use strict";
document.addEventListener("DOMContentLoaded", function() {
    var link = document.getElementById("backtotop");
    var screens = getComputedStyle(link).getPropertyValue('--screens');
    if (+screens==0) {
        link.style.opacity = "1";
        link.style.visibility = "visible"; // accessibility
    } else {
        window.addEventListener("scroll", function() {
            if ((document.body.scrollTop || document.documentElement.scrollTop) > screens*window.innerHeight) {
                link.style.opacity = "1";
                link.style.visibility = "visible"; // accessibility
            } else {
                link.style.opacity = "0";
                link.style.visibility = "hidden"; // accessibility
            }
        });
    }
});
