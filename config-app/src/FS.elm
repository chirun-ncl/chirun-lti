module FS exposing (FileType(..), FSItem, FileTree, FileFilter, decode, filter, extension)

{- A model of a file system consisting of a tree of directories and files. -}

import Dict exposing (Dict)
import Json.Decode as JD
import Tree exposing (Tree)

type FileType
    = Directory
    | File

type alias FSItem = { type_ : FileType, name : String}
type alias FileTree = Tree FSItem

{- Get the extension of a filename: the part after and including the last `.` character. -}
extension : FSItem -> String
extension = .name >> String.split (".") >> List.reverse >> List.head >> Maybe.map ((++) ".") >> Maybe.withDefault ""

type alias FileFilter = FSItem -> Bool

{- Filter a file tree, including only items that satisfy the filter function. -}
filter : FileFilter -> FileTree -> FileTree
filter test = 
    Tree.restructure
        identity
        (\l c -> Tree.tree l (List.filter (Tree.label >> test) c))

{- Decode a JSON encoding of a file tree: a tree of directories, with each node of the form:
    { "path": string
    , "dirs": a list of child directories
    , "files": a list of filenames
    }
-}
decode : JD.Decoder FileTree
decode =
    JD.map3
        (\path -> \dirs -> \files -> Tree.tree {type_ = Directory, name = path} (dirs++files))
        (JD.field "path" JD.string)
        (JD.field "dirs" (JD.list (JD.lazy (\_ -> decode))))
        (JD.field "files" (JD.string |> JD.map (\n -> Tree.singleton { type_ = File, name = n }) |> JD.list))
