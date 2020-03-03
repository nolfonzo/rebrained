def largestArrayCompareToMax(array):
    """Prints largest value in array by comparing to max - O(n) solution
    >>> largestArrayCompareToMax([1,5,9,2,4,6,8,3,7])
    9s
    """
    max = array[0]
    for i in range(len(array)):
        if array[i] > max: max = array[i]
    print max

if __name__ == "__main__":
    import doctest
    doctest.testmod()
