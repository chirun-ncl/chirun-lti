module Tree.Navigate.Extra exposing (move_right, move_up, move_down, alter_with_side_effect)

{- Extra functions for manipulating trees and forests by paths. -}

import Forest.Navigate as FN exposing (Forest)
import Forest.Path as FP exposing (ForestPath)
import List.Extra
import Tree exposing (Tree)
import Tree.Navigate as TN
import Tree.Path as TP
import Tree.Zipper as TZ
import Tuple exposing (pair)

tree_swap : TP.TreePath -> TP.TreePath -> Tree a -> Tree a
tree_swap a b tree =
    case (TN.to a tree, TN.to b tree) of
        (Just ta, Just tb) -> 
                tree
            |>  TN.restructure (\sub -> if sub.path == a then tb else if sub.path == b then ta else (Tree.tree sub.label sub.children))
        _ -> tree

path_to_zipper : TP.TreePath -> Tree a -> Maybe (TZ.Zipper a)
path_to_zipper path tree = 
    let
        topzip = TZ.fromTree tree
        step p z =
            case p of
                [] -> Just z
                a::rest ->
                       List.foldl (\_ z2 -> Maybe.andThen TZ.nextSibling z2) (TZ.firstChild z) (List.range 1 a)
                    |> Maybe.andThen (step rest)
    in
        step path topzip

zipper_to_path : TZ.Zipper a -> TP.TreePath
zipper_to_path z =
    case (TZ.parent z) of
        Nothing -> []
        Just pz ->
            let
                ppath = zipper_to_path pz
                n = TZ.siblingsBeforeFocus z |> List.length
            in
                ppath++[n]



alter_with_side_effect : Int -> (Tree a -> (Tree a, b)) -> Forest a -> (Forest a, Maybe b)
alter_with_side_effect i fn forest =
    forest
    |> List.indexedMap pair
    |> List.foldr (\(j,tree) (f2,b) -> if j==i then fn tree |> (\(t2,b2) -> (t2::f2, Just b2)) else (tree::f2, b)) ([],Nothing)

{- Move an item in a forest to be a child of its previous sibling, if that sibling can have children. -}
move_right : (a -> Bool) -> FP.ForestPath -> Forest a -> (Forest a, Maybe FP.ForestPath)
move_right has_children ((i,tpath) as path) forest = 
    case path of
        -- Can't move the first tree right.
        (0,[]) -> (forest, Nothing)

        -- Any other tree: make it a child of the previous tree
        (_,[]) -> case (List.Extra.getAt (i-1) forest, List.Extra.getAt i forest) of
            (Just ptree, Just tree) ->
                if has_children (Tree.label ptree) then
                    forest
                    |> alter_with_side_effect (i-1) (\t -> (Tree.tree (Tree.label t) ((Tree.children t)++[tree]), (i-1,[t |> Tree.children |> List.length])))
                    |> \(f,p) -> (List.Extra.removeAt i f, p)
                else
                    (forest, Nothing)

            _ -> (forest, Nothing)


        -- Other paths: moving within a single tree.
        _ -> 
            if (tpath |> List.reverse |> List.head) == Just 0 then 
                (forest, Nothing)
            else
                forest 
                |> alter_with_side_effect i 
                    (\tree ->
                        let
                            zipper = path_to_zipper tpath tree
                            prev = zipper |> Maybe.andThen (TZ.findPrevious has_children)
                        in
                            case (zipper, prev) of
                                (Just z, Just pz) ->
                                    let
                                        prev_path = zipper_to_path pz
                                        t = TZ.tree z
                                        p = TZ.label pz
                                    in
                                        if has_children p then
                                            let 
                                                step : { path : TP.TreePath, label : a, children : List (Bool,(Tree a, Maybe TP.TreePath)) } -> (Bool, (Tree a, Maybe TP.TreePath))
                                                step sub =
                                                    let
                                                        side_effect_children : List (Tree a, Maybe TP.TreePath)
                                                        side_effect_children = List.filterMap (\(keep,c) -> if keep then Just c else Nothing) sub.children

                                                        children : List (Tree a)
                                                        children = List.map Tuple.first side_effect_children

                                                        npath : Maybe TP.TreePath
                                                        npath = side_effect_children |> List.map Tuple.second |> List.filterMap identity |> List.head
                                                    in
                                                        if sub.path == List.take (List.length sub.path) tpath && (List.length tpath) - (List.length sub.path) <= 1 then 
                                                            (False, (Tree.tree sub.label children, Nothing))
                                                        else
                                                            if sub.path == prev_path then
                                                                (True, (Tree.tree sub.label (children++[t]), Just (sub.path++[List.length children])))
                                                            else
                                                                (True, (Tree.tree sub.label children, npath))
                                            in
                                                tree
                                                |> TN.restructure step
                                                |> Tuple.second
                                        else
                                            (tree, Nothing)

                                _ -> (tree, Nothing)
                    )
                |> \(f, mp) -> (f, Maybe.andThen identity mp |> Maybe.map (pair i))
            

{- Move an item in a forest up one space, returning the changed forest and the new path to the item
-}
move_up : FP.ForestPath -> Forest a -> (Forest a, Maybe FP.ForestPath)
move_up ((i,tpath) as path) forest = 
    let
        mtree = List.Extra.getAt i forest
        prev_path = mtree |> Maybe.andThen (\tree -> path_to_zipper tpath tree) |> Maybe.andThen TZ.backward |> Maybe.map zipper_to_path
    in
        case path of
        -- Can't move the first tree in the forest
        (0,[]) -> (forest, Nothing)

        -- Moving any other top-level tree means swap it with the one before.
        (_,[]) -> (List.Extra.swapAt (i-1) i forest, Just (i-1,tpath))

        -- Moving the first child of a tree means move it to become its own tree in the forest.
        (_,[0]) -> 
            case FN.to path forest of
                Just t ->((List.take i forest)++[t]++(FN.remove path forest |> List.drop i), Just (i,[]))
                Nothing -> (forest, Nothing)

        -- Moving anything else means move it within the tree
        _ -> case tpath |> List.reverse |> List.head of
            Just 0 -> -- move rest++[x,0] to rest++[x], remove rest++[x,0] from rest++[x]
                forest
                |> alter_with_side_effect i
                    (\tree -> 
                        tree
                        |> TN.restructure 
                            (\sub -> 
                                let
                                    children = List.map Tuple.first sub.children |> List.concatMap identity
                                    npath = sub.children |> List.map Tuple.second |> List.filterMap identity |> List.head
                                in
                                    if (tpath |> List.reverse |> List.drop 1 |> List.reverse) == sub.path then
                                        case List.head children of
                                            Just c -> ([c, Tree.tree sub.label (List.drop 1 children)], Just (sub.path))
                                            Nothing -> ([Tree.tree sub.label children], npath)
                                    else
                                        ([Tree.tree sub.label children], npath)
                            )
                        |> \(fs, mp) -> (fs |> List.head |> Maybe.withDefault tree, mp)
                    )
                |> \(f, mp) -> (f, mp |> Maybe.andThen identity |> Maybe.map (Tuple.pair i))
            Just n -> -- swap rest++[x+1] with rest++[x]
                forest
                |> alter_with_side_effect i
                    (\tree -> 
                        tree
                        |> TN.restructure 
                            (\sub -> 
                                let
                                    children = List.map Tuple.first sub.children
                                    npath = sub.children |> List.map Tuple.second |> List.filterMap identity |> List.head
                                in
                                    if (tpath |> List.reverse |> List.drop 1 |> List.reverse) == sub.path then
                                        (Tree.tree sub.label (List.Extra.swapAt (n-1) n children), Just (sub.path++[n-1]))
                                    else
                                        (Tree.tree sub.label children, npath)
                            )
                    )
                |> \(f, mp) -> (f, mp |> Maybe.andThen identity |> Maybe.map (Tuple.pair i))
            Nothing -> (forest, Nothing) -- empty list, can't happen

{- Increment the last component of a tree path. -}
increment_path : TP.TreePath -> TP.TreePath
increment_path path = case path of
    [] -> []
    a::[] -> [a+1]
    a::rest -> a::(increment_path rest)

{- Move an item in a forest one space down: either swap with its next sibling, or into the parent list, just after its current parent. -}
move_down : FP.ForestPath -> Forest a -> (Forest a, Maybe FP.ForestPath)
move_down ((fi,tpath) as path) forest =
    if fi == ((List.length forest) - 1) then 
        (forest, Nothing)
    else
        case tpath of
            [] -> (List.Extra.swapAt fi (fi+1) forest, Just (fi+1,tpath))
            _ ->
                   forest 
                |> List.indexedMap
                    (\fn tree -> 
                        if fn == fi then
                            tree 
                            |> TN.restructure
                                (\sub -> 
                                    let
                                        num_children = List.length children
                                        li = num_children - 1
                                        mi = List.indexedMap pair children |> List.Extra.find (\(i,c) -> sub.path++[i] == tpath)
                                        children = sub.children |> List.map Tuple.first |> List.concatMap identity
                                        child_path = sub.children |> List.filterMap Tuple.second |> List.head
                                    in
                                        case mi of
                                            Just (i,c) -> 
                                                if i == li then
                                                    ( [Tree.tree sub.label (List.take li children)]++[c]
                                                    , Just <| increment_path sub.path
                                                    )
                                                else
                                                    ( [Tree.tree sub.label (List.Extra.swapAt i (i+1) sub.children |> List.map Tuple.first |> List.concatMap identity)]
                                                    , Just <| sub.path++[i+1]
                                                    )

                                            Nothing -> ([Tree.tree sub.label children], child_path)
                                )
                            |> (\(trees,mpath) -> 
                                ( trees
                                , case (trees,mpath) of
                                    (_,Nothing) -> Nothing
                                    (x::[],Just npath) -> Just (fi,npath)
                                    (a::b::_,Just npath) -> Just (fi+1,[])
                                    _ -> Nothing
                                )
                                )

                        else
                            ([tree], Nothing)
                    )
                |> (\f -> (f |> List.map Tuple.first |> List.concatMap identity, f |> List.map Tuple.second |> List.filterMap identity |> List.head))
