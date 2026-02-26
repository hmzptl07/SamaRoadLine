"use strict";

/* tooltip */
const tooltipTriggerList = document.querySelectorAll(
  '[data-bs-toggle="tooltip"]'
);
const tooltipList = [...tooltipTriggerList].map(
  (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
);

/* popover  */
const popoverTriggerList = document.querySelectorAll(
  '[data-bs-toggle="popover"]'
);
const popoverList = [...popoverTriggerList].map(
  (popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl)
);

/* Choices JS */
document.addEventListener("DOMContentLoaded", function () {
  var genericExamples = document.querySelectorAll("[data-trigger]");
  for (let i = 0; i < genericExamples.length; ++i) {
    var element = genericExamples[i];
    new Choices(element, {
      allowHTML: true,
      placeholderValue: "This is a placeholder set in the config",
      searchPlaceholderValue: "Search",
    });
  }
});
/* Choices JS */

//switcher color pickers
const pickrContainerPrimary = document.querySelector(
  ".pickr-container-primary"
);
const themeContainerPrimary = document.querySelector(
  ".theme-container-primary"
);
/* for theme primary */
const nanoThemes = [
  [
    "nano",
    {
      defaultRepresentation: "RGB",
      components: {
        preview: true,
        opacity: false,
        hue: true,

        interaction: {
          hex: false,
          rgba: true,
          hsva: false,
          input: true,
          clear: false,
          save: false,
        },
      },
    },
  ],
];
const nanoButtons = [];
let nanoPickr = null;
for (const [theme, config] of nanoThemes) {
  const button = document.createElement("button");
  button.innerHTML = theme;
  nanoButtons.push(button);

  button.addEventListener("click", () => {
    const el = document.createElement("p");
    pickrContainerPrimary.appendChild(el);

    /* Delete previous instance */
    if (nanoPickr) {
      nanoPickr.destroyAndRemove();
    }

    /* Apply active class */
    for (const btn of nanoButtons) {
      btn.classList[btn === button ? "add" : "remove"]("active");
    }

    /* Create fresh instance */
    nanoPickr = new Pickr(
      Object.assign(
        {
          el,
          theme,
          default: "rgb(84, 109, 254)",
        },
        config
      )
    );

    /* Set events */
    nanoPickr.on("changestop", (source, instance) => {
      let color = instance.getColor().toRGBA();
      let html = document.querySelector("html");
      html.style.setProperty(
        "--primary-rgb",
        `${Math.floor(color[0])}, ${Math.floor(color[1])}, ${Math.floor(
          color[2]
        )}`
      );
      /* theme color picker */
      localStorage.setItem(
        "primaryRGB",
        `${Math.floor(color[0])}, ${Math.floor(color[1])}, ${Math.floor(
          color[2]
        )}`
      );
      updateColors();
    });
  });

  themeContainerPrimary.appendChild(button);
}
nanoButtons[0].click();
/* for theme primary */

/* footer year */
document.getElementById("year").innerHTML = new Date().getFullYear();
/* footer year */

let mainContent;
(function () {
  let html = document.querySelector("html");
  mainContent = document.querySelector(".main-content");

  localStorageBackup();
  switcherClick();
  checkOptions();
  /* LTR to RTL */
  // html.setAttribute("dir" , "rtl") // for rtl version
})();

function switcherClick() {
  let ltrBtn,
    rtlBtn,
    lightBtn,
    darkBtn,
    primaryDefaultColor1Btn,
    primaryDefaultColor2Btn,
    primaryDefaultColor3Btn,
    primaryDefaultColor4Btn,
    primaryDefaultColor5Btn,
    ResetAll;
  let html = document.querySelector("html");
  lightBtn = document.querySelector("#switcher-light-theme");
  darkBtn = document.querySelector("#switcher-dark-theme");
  ltrBtn = document.querySelector("#switcher-ltr");
  rtlBtn = document.querySelector("#switcher-rtl");
  primaryDefaultColor1Btn = document.querySelector("#switcher-primary");
  primaryDefaultColor2Btn = document.querySelector("#switcher-primary1");
  primaryDefaultColor3Btn = document.querySelector("#switcher-primary2");
  primaryDefaultColor4Btn = document.querySelector("#switcher-primary3");
  primaryDefaultColor5Btn = document.querySelector("#switcher-primary4");
  ResetAll = document.querySelector("#reset-all");

  /* Light Layout Start */
  let lightThemeVar = lightBtn.addEventListener("click", () => {
    lightFn();
    localStorage.setItem("zenoHeader", "light");
    localStorage.setItem("zenoMenu", "light");
  });
  /* Light Layout End */

  /* Dark Layout Start */
  let darkThemeVar = darkBtn.addEventListener("click", () => {
    darkFn();
    localStorage.setItem("zenoMenu", "dark");
    localStorage.setItem("zenoHeader", "dark");
  });
  /* Dark Layout End */

  // primary theme
  let primaryColor1Var = primaryDefaultColor1Btn.addEventListener(
    "click",
    () => {
      localStorage.setItem("primaryRGB", "64, 100, 221");
      html.style.setProperty("--primary-rgb", `64, 100, 221`);
      updateColors();
    }
  );
  if(localStorage.primaryRGB == "64, 100, 221"){
    document.querySelector('#switcher-primary').checked = true;
  }
  let primaryColor2Var = primaryDefaultColor2Btn.addEventListener(
    "click",
    () => {
      localStorage.setItem("primaryRGB", "207, 117, 225");
      html.style.setProperty("--primary-rgb", `207, 117, 225`);
      updateColors();
    }
  );
  if(localStorage.primaryRGB == "207, 117, 225"){
    document.querySelector('#switcher-primary1').checked = true;
  }
  let primaryColor3Var = primaryDefaultColor3Btn.addEventListener(
    "click",
    () => {
      localStorage.setItem("primaryRGB", "199, 89, 106");
      html.style.setProperty("--primary-rgb", `199, 89, 106`);
      updateColors();
    }
  );
  if(localStorage.primaryRGB == "199, 89, 106"){
    document.querySelector('#switcher-primary2').checked = true;
  }
  let primaryColor4Var = primaryDefaultColor4Btn.addEventListener(
    "click",
    () => {
      localStorage.setItem("primaryRGB", "1, 159, 162");
      html.style.setProperty("--primary-rgb", `1, 159, 162`);
      updateColors();
    }
  );
  if(localStorage.primaryRGB == "1, 159, 162"){
    document.querySelector('#switcher-primary3').checked = true;
  }
  let primaryColor5Var = primaryDefaultColor5Btn.addEventListener(
    "click",
    () => {
      localStorage.setItem("primaryRGB", "139, 149, 4");
      html.style.setProperty("--primary-rgb", `139, 149, 4`);
      updateColors();
    }
  );
  if(localStorage.primaryRGB == "139, 149, 4"){
    document.querySelector('#switcher-primary4').checked = true;
  }

  /* rtl start */
  let rtlVar = rtlBtn.addEventListener("click", () => {
    localStorage.setItem("zenortl", true);
    localStorage.removeItem("zenoltr");
    rtlFn();
  });
  /* rtl end */

  /* ltr start */
  let ltrVar = ltrBtn.addEventListener("click", () => {
    //    local storage
    localStorage.setItem("zenoltr", true);
    localStorage.removeItem("zenortl");
    ltrFn();
  });
  /* ltr end */

  // reset all start
  let resetVar = ResetAll.addEventListener("click", () => {
    // clear primary & bg color
    html.style.removeProperty(`--primary-rgb`);

    // clear rtl
    html.removeAttribute("dir", "rtl");
    html.setAttribute("dir", "ltr");

    ResetAllFn();
  });
  // reset all start
}

function ltrFn() {
  let html = document.querySelector("html");
  document
    .querySelector("#style")
    ?.setAttribute("href", "../assets/libs/bootstrap/css/bootstrap.min.css");
  html.setAttribute("dir", "ltr");
  document.querySelector("#switcher-ltr").checked = true;
  checkOptions();
}

function rtlFn() {
  let html = document.querySelector("html");
  html.setAttribute("dir", "rtl");
  document
    .querySelector("#style")
    ?.setAttribute(
      "href",
      "../assets/libs/bootstrap/css/bootstrap.rtl.min.css"
    );
  checkOptions();
}

if (localStorage.zenortl) {
  rtlFn();
}

function lightFn() {
  let html = document.querySelector("html");
  html.setAttribute("data-theme-mode", "light");
  document.querySelector("#switcher-light-theme").checked = true;
  updateColors();
  localStorage.removeItem("zenodarktheme");
  checkOptions();
  // html.style.removeProperty('--primary-rgb');
}

function darkFn() {
  let html = document.querySelector("html");
  html.setAttribute("data-theme-mode", "dark");
  updateColors();
  localStorage.setItem("zenodarktheme", true);
  localStorage.removeItem("zenolighttheme");
  checkOptions();
  // html.style.removeProperty("--primary-rgb");
}

function ResetAllFn() {
  let html = document.querySelector("html");
  checkOptions();

  // clearing localstorage
  localStorage.clear();

  // reseting chart colors
  updateColors();

  // reseting rtl
  ltrFn();

  // reseting dark theme
  lightFn();

  // resetting theme primary
  document.querySelector("#switcher-primary").checked = false
  document.querySelector("#switcher-primary1").checked = false
  document.querySelector("#switcher-primary2").checked = false
  document.querySelector("#switcher-primary3").checked = false
  document.querySelector("#switcher-primary4").checked = false
}

function checkOptions() {
  // dark
  if (localStorage.getItem("zenodarktheme")) {
    document.querySelector("#switcher-dark-theme").checked = true;
  }

  //RTL
  if (localStorage.getItem("zenortl")) {
    document.querySelector("#switcher-rtl").checked = true;
  }
}

// chart colors
let myVarVal, primaryRGB;
function updateColors() {
  "use strict";
  primaryRGB = getComputedStyle(document.documentElement)
    .getPropertyValue("--primary-rgb")
    .trim();
}
updateColors();

function localStorageBackup() {
  if (localStorage.primaryRGB) {
    if (document.querySelector(".theme-container-primary")) {
      document.querySelector(".theme-container-primary").value =
        localStorage.primaryRGB;
    }
    document
      .querySelector("html")
      .style.setProperty("--primary-rgb", localStorage.primaryRGB);
  }
  if (localStorage.zenodarktheme) {
    let html = document.querySelector("html");
    html.setAttribute("data-theme-mode", "dark");
  }

  if (localStorage.zenortl) {
    let html = document.querySelector("html");
    html.setAttribute("dir", "rtl");
  }
  if (localStorage.zenolayout) {
    let html = document.querySelector("html");
    let layoutValue = localStorage.getItem("zenolayout");
    html.setAttribute("data-nav-layout", "horizontal");
  }
}
// section menu active
function onScroll(event) {
  const sections = document.querySelectorAll(".side-menu__item");
  const scrollPos =
    window.pageYOffset ||
    document.documentElement.scrollTop ||
    document.body.scrollTop;

  sections.forEach((elem) => {
    const val = elem.getAttribute("href");
    let refElement;
    if (val != "javascript:void(0);" && val !== "#") {
      refElement = document.querySelector(val);
    }
    const scrollTopMinus = scrollPos + 73;
    if (
      refElement?.offsetTop <= scrollTopMinus &&
      refElement?.offsetTop + refElement.offsetHeight > scrollTopMinus
    ) {
      if (elem.parentElement.parentElement.classList.contains("child1")) {
        elem.parentElement.parentElement.parentElement.children[0].classList.add(
          "active"
        );
      }
      elem.classList.add("active");
      if (elem.closest(".child1")?.previousElementSibling) {
        elem.closest(".child1").previousElementSibling.classList.add("active");
      }
    } else {
      elem.classList.remove("active");
    }
  });
}
window.document.addEventListener("scroll", onScroll);
// for menu target scroll on click

/* back to top */
const scrollToTop = document.querySelector(".scrollToTop");
const $rootElement = document.documentElement;
const $body = document.body;
window.onscroll = () => {
  const scrollTop = window.scrollY || window.pageYOffset;
  const clientHt = $rootElement.scrollHeight - $rootElement.clientHeight;
  if (window.scrollY > 100) {
    scrollToTop.style.display = "flex";
  } else {
    scrollToTop.style.display = "none";
  }
};
scrollToTop.onclick = () => {
  window.scrollTo(0, 0);
};
/* back to top */

    // swiper with navigation
    var swiper = new Swiper(".swiper-navigation", {
      navigation: {
          nextEl: ".swiper-button-next",
          prevEl: ".swiper-button-prev",
      },
      loop: true,
      // autoplay: {
      //     delay: 1500,
      //     disableOnInteraction: false,
      // }
  });

/* team swiper */
var swiper = new Swiper(".teamSwiper", {
  slidesPerView: 3,
  spaceBetween: 30,
  pagination: {
      el: ".swiper-pagination",
      dynamicBullets: true,
      clickable: true,
  },
  loop: true,
  autoplay: {
      delay: 1500,
      disableOnInteraction: false
  },
  breakpoints: {
    320: {
      slidesPerView: 1,
      spaceBetween: 0,
    },
    480: {
      slidesPerView: 2,
      spaceBetween: 10,
    },
    1112: {
      slidesPerView: 3,
      spaceBetween: 15,
    },
    1300: {
      slidesPerView: 3,
      spaceBetween: 30,
    }
  },
});
/* team swiper */


 /* testimonial swiper service start */
 var swiper = new Swiper(".testimonialSwiperService", {
  slidesPerView: 3,
  spaceBetween: 30,
  loop: true,
  loopFillGroupWithBlank: true,
  pagination: {
      el: ".swiper-pagination",
      dynamicBullets: true,
      clickable: true,
  },
  autoplay: {
      enabled: true,
      delay: 3000,  
      disableOnInteraction: false,
  },
  breakpoints: {
      320: {
          slidesPerView: 1,
          spaceBetween: 10,
      },
      480: {
          slidesPerView: 1,
          spaceBetween: 10,
      },
      1112: {
          slidesPerView: 3,
          spaceBetween: 10,
      },
      1300: {
          slidesPerView: 2,
          spaceBetween: 30,
      }
  },
});
