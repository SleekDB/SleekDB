<h1 align="center">Please give it a Star if you like the project 🎉 <img width="100" src="https://i.imgur.com/YaY5arh.gif"> ❤️<h1>

<p align="center">
<a href="https://sleekdb.github.io/"><img src="https://sleekdb.github.io/assets/SleekDB-Logo.jpg"></a>
</p>

# SleekDB - A NoSQL Database made using PHP

## Full documentation: https://sleekdb.github.io/

SleekDB is a simple flat file NoSQL like database implemented in PHP without any third-party dependencies that store data in plain JSON files.

It is not designed to handle heavy-load IO operations, it is designed to have a simple solution where all we need a database for managing a few gigabytes of data. You can think of it as a database for low to medium operation loads.

## Features

- ⚡ **Lightweight, faster**

  Stores data in plain-text utilizing JSON format, no binary conversion needed to store or fetch the data. Default query cache layer.

- 🔆 **Schema free data storage**

  SleekDB does not require any schema, so you can insert any types of data you want.

- 🔍 **Query on nested properties**

  It supports schema free data, so you can filter and use conditions on nested properties of the JSON documents!

  ```php
  where( 'post.author.role', '=', 'admin' )
  ```

  SleekDB will look for data at:

  ```php
  {
    "post": {
      "author": {
        "role": "admin"
      }
    }
  }
  ```

- ✨ **Dependency free, only needs PHP to run**

  Supports PHP 7+. Requires no third-party plugins or software.

- 🚀 **Default caching layer**

  SleekDB will serve data from cache by default and regenerate cache automatically! Query results will be cached and later reused from a single file instead of traversing all the available files.

- 🌈 **Rich Conditions and Filters**

  Use multiple conditional comparisons, text search, sorting on multiple properties and nested properties. Some useful methods are:

  <table>
  <tbody>
    <tr>
      <td valign="top">
        <ul>
          <li>where</li>
          <li>orWhere</li>
          <li>select</li>
          <li>except</li>
          <li>in</li>
          <li>not in</li>
        </ul>
      </td>
      <td valign="top">
        <ul>
          <li>join</li>
          <li>like</li>
          <li>sort</li>
          <li>skip</li>
          <li>orderBy</li>
          <li>update</li>
        </ul>
      </td>
      <td valign="top">
        <ul>
          <li>limit</li>
          <li>search</li>
          <li>distinct</li>
          <li>exists</li>
          <li>first</li>
          <li>delete</li>
        </ul>
      </td>
      <td valign="top">
        <ul>
          <li>like</li>
          <li>not lik</li>
          <li>between</li>
          <li>not between</li>
          <li>group by</li>
          <li>having</li>
        </ul>
      </td>
    </tr>
  </tbody>
  </table>

- 👍 **Process data on demand**
  
  SleekDB does not require any background process or network protocol in order to process data when you use it in a PHP project. All data for a query will be fetched at runtime within the same PHP process.

- 😍 **Runs everywhere**
  
  Runs perfectly on shared-servers or VPS too.


- 🍰 **Easy to learn and implement**

  SleekDB provides a very simple elegant API to handle all of your data.

- 🍰 **Easily import/export or backup data**
  
  SleekDB use files to store information. That makes tasks like backup, import and export very easy.

- 💪 **Actively maintained**

  <p>SleekDB is created by <a rel="noopener nofollow" href="https://twitter.com/rakibtg" target="_blank">@rakibtg</a> who is using it in various types of applications which are in production right now. Our other contributor and active maintainer is <a rel="noopener nofollow" href="https://www.goodsoft.de" target="_blank">Timucin</a> who is making SleekDB much better in terms of code quality and new features.</p>

- 📔 **Well documented**
  
  The <a href="https://sleekdb.github.io/">official documentation of SleekDB</a> does not just provide a good api documentation. It is filled with examples!

<h2 align="center">Visit our website https://sleekdb.github.io/ for documentation and getting started guide.</h2>
