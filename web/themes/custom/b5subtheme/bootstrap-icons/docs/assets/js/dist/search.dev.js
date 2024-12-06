"use strict";

var _fuse = _interopRequireDefault(require("fuse.js"));

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { "default": obj }; }

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance"); }

function _iterableToArray(iter) { if (Symbol.iterator in Object(iter) || Object.prototype.toString.call(iter) === "[object Arguments]") return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) { for (var i = 0, arr2 = new Array(arr.length); i < arr.length; i++) { arr2[i] = arr[i]; } return arr2; } }

(function () {
  var iconsBody = document.querySelector('#icons-body');
  if (!iconsBody) return;
  var searchInput = iconsBody.querySelector('#search');
  var iconListContainer = iconsBody.querySelector('#icons-list');
  var iconElementList = Array.from(iconListContainer.children);
  var iconDataList = iconElementList.map(function (element) {
    return {
      name: element.dataset.name,
      categories: element.dataset.categories.split(' '),
      tags: element.dataset.tags.split(' ')
    };
  });
  var fuse = new _fuse["default"](iconDataList, {
    ignoreLocation: true,
    useExtendedSearch: true,
    shouldSort: false,
    keys: ['name', 'categories', 'tags'],
    threshold: 0
  });

  function search(searchTerm) {
    var trimmedSearchTerm = searchTerm ? searchTerm.trim() : '';
    iconListContainer.innerHTML = '';

    if (trimmedSearchTerm.length > 0) {
      var searchResult = fuse.search(trimmedSearchTerm);
      var resultElements = searchResult.map(function (result) {
        return iconElementList[result.refIndex];
      });
      iconListContainer.append.apply(iconListContainer, _toConsumableArray(resultElements));
    } else {
      iconListContainer.append.apply(iconListContainer, _toConsumableArray(iconElementList));
    }

    var newUrl = new URL(window.location);

    if (trimmedSearchTerm.length > 0) {
      newUrl.searchParams.set('q', trimmedSearchTerm);
    } else {
      newUrl.searchParams["delete"]('q');
    }

    window.history.replaceState(null, null, newUrl);
  }

  var timeout;
  searchInput.addEventListener('input', function () {
    clearTimeout(timeout);
    timeout = setTimeout(function () {
      search(searchInput.value);
    }, 250);
  });
  var query = new URLSearchParams(window.location.search).get('q');
  if (!query || query.length === 0) return;
  var trimmedQuery = query.trim();
  search(trimmedQuery);
  searchInput.value = trimmedQuery;
  document.querySelector('#content').scrollIntoView();
})();